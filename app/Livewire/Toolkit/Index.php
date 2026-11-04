<?php

namespace App\Livewire\Toolkit;

use App\Models\StandalonePostgresql;
use App\Services\Toolkit\ArchiveImporter;
use App\Services\Toolkit\DatabaseUrl;
use App\Services\Toolkit\DnsLookup;
use App\Services\Toolkit\DomainLookup;
use App\Services\Security\MalwareScanner;
use App\Services\Toolkit\HardeningScript;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class Index extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public string $tab = 'domains';

    /* Domains ------------------------------------------------------------- */

    #[Validate(['nullable', 'string', 'max:253'])]
    public ?string $domainQuery = '';

    public array $domainResult = [];

    public array $whoisResult = [];

    public array $dnsRecords = [];

    public ?string $embeddedIp = null;

    #[Validate(['nullable', 'string', 'max:63'])]
    public ?string $siteLabel = '';

    public array $tldResults = [];

    public ?string $generatedHost = null;

    public ?string $dnsInstruction = null;

    public ?string $wildcardDomain = null;

    /* Import -------------------------------------------------------------- */

    #[Validate(['nullable', 'file', 'max:204800'])]
    public $archive = null;

    public array $importReport = [];

    public bool $malwareScanningEnabled = false;

    public bool $malwareScanningRequired = false;

    /* Database ------------------------------------------------------------ */

    public array $databases = [];

    public ?string $selectedDatabase = null;

    public string $connectionTarget = 'prisma';

    public bool $externalConnection = false;

    public ?string $connectionString = null;

    /* Hardening ----------------------------------------------------------- */

    public string $sshPort = '22';

    public array $hardeningSections = ['firewall', 'fail2ban', 'ddos', 'telemetry'];

    public ?string $hardeningScript = null;

    public function mount(): void
    {
        $this->wildcardDomain = config('toolkit.wildcard_domain');

        $scanner = MalwareScanner::fromConfig();
        $this->malwareScanningEnabled = $scanner->enabled();
        $this->malwareScanningRequired = $scanner->required();
        $this->databases = StandalonePostgresql::ownedByCurrentTeam()
            ->get()
            ->map(fn (StandalonePostgresql $database) => [
                'uuid' => $database->uuid,
                'name' => $database->name,
            ])
            ->all();
        $this->selectedDatabase = $this->databases[0]['uuid'] ?? null;
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['domains', 'import', 'database', 'hardening'], true)) {
            $this->tab = $tab;
        }
    }

    public function lookupDomain(DomainLookup $domains, DnsLookup $dns): void
    {
        $this->validateOnly('domainQuery');
        $this->whoisResult = [];
        $domain = strtolower(trim((string) $this->domainQuery));

        if ($domain === '') {
            $this->addError('domainQuery', 'Enter a domain name, such as example.com.');

            return;
        }

        $this->domainResult = $domains->rdap($domain);
        $this->dnsRecords = $dns->records($domain);
        $this->embeddedIp = $dns->embeddedIp($domain);
    }

    public function lookupWhois(DomainLookup $domains): void
    {
        $this->validateOnly('domainQuery');
        $this->whoisResult = $domains->whois((string) $this->domainQuery);
    }

    public function searchDomains(DomainLookup $domains): void
    {
        $this->validateOnly('siteLabel');
        $this->tldResults = $domains->search((string) $this->siteLabel);
    }

    public function generateHostname(): void
    {
        $label = Str::slug((string) $this->siteLabel);

        if ($label === '') {
            $label = 'app';
        }

        $host = $label.'-'.Str::lower(Str::random(6));
        $wildcard = is_string($this->wildcardDomain) ? trim($this->wildcardDomain, '.') : '';

        if ($wildcard !== '') {
            $host .= '.'.$wildcard;
            $this->dnsInstruction = 'Create or keep a wildcard record (*.'.$wildcard.') pointing at this server. '
                .'The hostname itself carries no IP address, so it is safe to share.';
        } else {
            $this->dnsInstruction = 'No wildcard domain is set. Create an A record for '.$host.' pointing at this server, '
                .'or set DASHY_WILDCARD_DOMAIN to a domain you own.';
        }

        $this->generatedHost = $host;
    }

    public function importArchive(ArchiveImporter $importer): void
    {
        $this->validateOnly('archive');

        if (! $this->archive) {
            $this->addError('archive', 'Choose a zip file to import.');

            return;
        }

        if (strtolower($this->archive->getClientOriginalExtension()) !== 'zip') {
            $this->addError('archive', 'Only zip archives are supported.');

            return;
        }

        $destination = storage_path('app/toolkit-imports/'.Str::uuid());

        try {
            $extracted = $importer->extract($this->archive->getRealPath(), $destination, MalwareScanner::fromConfig());
            $stack = $importer->detectStack($extracted['root']);

            $this->importReport = [
                'files' => $extracted['files'],
                'bytes' => $extracted['bytes'],
                'path' => $extracted['root'],
                'stack' => $stack,
            ];
            $this->archive = null;
        } catch (\Throwable $e) {
            $this->addError('archive', $e->getMessage());
        }
    }

    public function discardImport(): void
    {
        $path = $this->importReport['path'] ?? null;

        if (is_string($path) && str_contains($path, 'toolkit-imports')) {
            File::deleteDirectory(dirname($path));
        }

        $this->importReport = [];
    }

    public function buildConnectionString(DatabaseUrl $builder): void
    {
        $this->connectionString = null;

        $database = StandalonePostgresql::ownedByCurrentTeam()
            ->where('uuid', $this->selectedDatabase)
            ->first();

        if (! $database) {
            $this->addError('connectionString', 'Choose a PostgreSQL database.');

            return;
        }

        if (! auth()->user()->isAdminOfTeam($database->team()->id)) {
            $this->addError('connectionString', 'Only a team admin or owner can read database credentials.');

            return;
        }

        $url = $builder->fromPostgres($database, $this->connectionTarget, $this->externalConnection);

        if ($url === null) {
            $this->addError('connectionString', 'This database has no public port. Publish the port first, or use the internal connection.');

            return;
        }

        $this->connectionString = $url;
    }

    public function generateHardening(HardeningScript $hardening): void
    {
        $this->hardeningScript = $hardening->render($this->hardeningSections, $this->sshPort);
    }

    public function render()
    {
        return view('livewire.toolkit.index');
    }
}
