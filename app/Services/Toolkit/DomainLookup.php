<?php

namespace App\Services\Toolkit;

use Illuminate\Support\Facades\Http;

class DomainLookup
{
    /**
     * TLDs offered by the domain searcher. Kept small on purpose: every probe
     * is a network round trip, and a search should stay fast.
     *
     * @var array<int, string>
     */
    public const SEARCH_TLDS = ['com', 'net', 'org', 'io', 'dev', 'app', 'fr', 'eu'];

    private const WHOIS_MAX_BYTES = 200000;

    public function isValidDomain(string $domain): bool
    {
        $domain = strtolower(trim($domain));

        if (strlen($domain) > 253 || ! str_contains($domain, '.')) {
            return false;
        }

        return (bool) preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain);
    }

    /**
     * Look a domain up over RDAP. RDAP is HTTPS, structured, and returns a
     * clean 404 for an unregistered name, so it is the primary source.
     *
     * @return array{status: string, domain?: string, registrar?: ?string, created?: ?string, expires?: ?string, updated?: ?string, nameservers?: array<int, string>, error?: ?string}
     */
    public function rdap(string $domain): array
    {
        $domain = strtolower(trim($domain));

        if (! $this->isValidDomain($domain)) {
            return ['status' => 'invalid', 'error' => 'Enter a valid domain name, such as example.com.'];
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'Dashy Domain Toolkit'])
                ->get('https://rdap.org/domain/'.$domain);
        } catch (\Throwable $e) {
            return ['status' => 'unknown', 'domain' => $domain, 'error' => 'The registry could not be reached.'];
        }

        if ($response->status() === 404) {
            return ['status' => 'available', 'domain' => $domain, 'error' => null];
        }

        if ($response->status() === 429) {
            return ['status' => 'unknown', 'domain' => $domain, 'error' => 'The registry rate limit was reached. Try again in a minute.'];
        }

        if ($response->failed()) {
            return ['status' => 'unknown', 'domain' => $domain, 'error' => 'The registry returned an unexpected response.'];
        }

        $data = $response->json();

        if (! is_array($data) || empty($data['ldhName'])) {
            return ['status' => 'unknown', 'domain' => $domain, 'error' => 'The registry response could not be read.'];
        }

        return [
            'status' => 'registered',
            'domain' => $domain,
            'registrar' => $this->registrarFromRdap($data),
            'created' => $this->eventDate($data, 'registration'),
            'expires' => $this->eventDate($data, 'expiration'),
            'updated' => $this->eventDate($data, 'last changed'),
            'nameservers' => $this->nameserversFromRdap($data),
            'error' => null,
        ];
    }

    /**
     * Check one label across several TLDs.
     *
     * @param  array<int, string>  $tlds
     * @return array<int, array{tld: string, domain: string, status: string}>
     */
    public function search(string $label, array $tlds = self::SEARCH_TLDS): array
    {
        $label = strtolower(trim($label));
        $label = preg_replace('/[^a-z0-9-]/', '', $label) ?? '';

        if ($label === '') {
            return [];
        }

        $results = [];

        foreach (array_slice($tlds, 0, 8) as $tld) {
            $domain = $label.'.'.ltrim(strtolower($tld), '.');
            $results[] = [
                'tld' => strtolower($tld),
                'domain' => $domain,
                'status' => $this->rdap($domain)['status'],
            ];
        }

        return $results;
    }

    /**
     * Port-43 WHOIS, used as a fallback and for the raw record view.
     * Many registries rate limit or omit fields, so the raw text is returned
     * for the user to read instead of being over-parsed.
     *
     * @return array{status: string, domain: string, server?: ?string, raw?: string, error?: ?string}
     */
    public function whois(string $domain): array
    {
        $domain = strtolower(trim($domain));

        if (! $this->isValidDomain($domain)) {
            return ['status' => 'invalid', 'domain' => $domain, 'error' => 'Enter a valid domain name, such as example.com.'];
        }

        $tld = substr($domain, (int) strrpos($domain, '.') + 1);
        $server = $this->whoisServerForTld($tld);

        if ($server === null) {
            return ['status' => 'unknown', 'domain' => $domain, 'error' => 'No WHOIS server is known for this extension.'];
        }

        $raw = $this->queryWhois($server, $domain);

        if ($raw === null) {
            return ['status' => 'unknown', 'domain' => $domain, 'server' => $server, 'error' => 'The WHOIS server did not answer.'];
        }

        if (preg_match('/(no match for|not found|no data found|no entries found|domain not found|status:\s*free)/i', $raw)) {
            $status = 'available';
        } elseif (preg_match('/(domain name:|registry domain id:|creation date:|registrar:)/i', $raw)) {
            $status = 'registered';
        } else {
            $status = 'unknown';
        }

        return [
            'status' => $status,
            'domain' => $domain,
            'server' => $server,
            'raw' => trim(substr($raw, 0, 8000)),
            'error' => null,
        ];
    }

    /**
     * Ask IANA which WHOIS server serves the TLD, then read its "refer" line.
     */
    private function whoisServerForTld(string $tld): ?string
    {
        $response = $this->queryWhois('whois.iana.org', $tld);

        if ($response !== null && preg_match('/^refer:\s*(\S+)/mi', $response, $matches)) {
            return trim($matches[1]);
        }

        $known = [
            'com' => 'whois.verisign-grs.com',
            'net' => 'whois.verisign-grs.com',
            'org' => 'whois.pir.org',
            'io' => 'whois.nic.io',
            'dev' => 'whois.nic.google',
            'app' => 'whois.nic.google',
            'fr' => 'whois.nic.fr',
            'eu' => 'whois.eu',
        ];

        return $known[$tld] ?? null;
    }

    private function queryWhois(string $server, string $query): ?string
    {
        $socket = @fsockopen($server, 43, $errno, $errstr, 6);

        if ($socket === false) {
            return null;
        }

        stream_set_timeout($socket, 8);
        fwrite($socket, $query."\r\n");

        $response = '';

        while (! feof($socket)) {
            $chunk = fread($socket, 4096);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $response .= $chunk;

            if (strlen($response) >= self::WHOIS_MAX_BYTES) {
                break;
            }
        }

        fclose($socket);

        return $response === '' ? null : $response;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function registrarFromRdap(array $data): ?string
    {
        foreach ($data['entities'] ?? [] as $entity) {
            if (! is_array($entity) || ! in_array('registrar', $entity['roles'] ?? [], true)) {
                continue;
            }

            foreach ($entity['vcardArray'][1] ?? [] as $field) {
                if (is_array($field) && ($field[0] ?? null) === 'fn' && is_string($field[3] ?? null)) {
                    return $field[3];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function eventDate(array $data, string $action): ?string
    {
        foreach ($data['events'] ?? [] as $event) {
            if (is_array($event) && ($event['eventAction'] ?? null) === $action) {
                return is_string($event['eventDate'] ?? null) ? $event['eventDate'] : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function nameserversFromRdap(array $data): array
    {
        $nameservers = [];

        foreach ($data['nameservers'] ?? [] as $nameserver) {
            $name = is_array($nameserver) ? ($nameserver['ldhName'] ?? null) : null;

            if (is_string($name) && $name !== '') {
                $nameservers[] = strtolower($name);
            }
        }

        return array_values(array_unique($nameservers));
    }
}
