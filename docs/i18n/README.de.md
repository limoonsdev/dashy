[English](../../README.md) · [Français](./README.fr.md) · [Español](./README.es.md) · **Deutsch** · [日本語](./README.ja.md) · [简体中文](./README.zh-cn.md)

<div align="center">

<img src="../assets/dashy-mark.svg" alt="Dashy" width="88" />

# Dashy

**Die Control Plane, die dir gehört.** Anwendungen, Datenbanken, Dienste, Server und virtuelle Maschinen in einem Dashboard.

</div>

## Überblick

Dashy wird einmal installiert und bleibt in deiner Infrastruktur. Es deployt Anwendungen aus Git, verwaltet Datenbanken, installiert Dienste mit einem Klick, administriert Server über SSH und erstellt virtuelle Maschinen auf Proxmox VE.

Auf verwalteten Hosts ist kein Agent nötig: SSH genügt. Wenn du Dashy nicht mehr nutzt, laufen deine Workloads weiter und bleiben von Hand administrierbar.

## Installation

```bash
curl -fsSL https://raw.githubusercontent.com/limoonsdev/dashy/main/scripts/install.sh | sudo bash
```

Der Installer prüft den Host, installiert Docker bei Bedarf, baut oder lädt das Image, startet den Stack und zeigt die Dashboard-Adresse. Danach `http://<server>:8000` öffnen und das erste Konto anlegen — es wird Instanz-Administrator.

## Funktionen

| Bereich | Details |
| --- | --- |
| Anwendungen | Git, Dockerfile, Nixpacks, Railpack, docker-compose, Vorschau-Deployments pro Branch, sofortiges Zurückrollen |
| Datenbanken | PostgreSQL, MySQL, MariaDB, MongoDB, Redis, KeyDB, ClickHouse, Dragonfly, S3-Backups |
| Dienste | Ein-Klick-Katalog, ohne eigene Compose-Datei |
| Server | Hinzufügen per SSH, Docker und Proxy automatisch, Metriken ohne Agent |
| Virtuelle Maschinen | Distributionsauswahl, automatische Dimensionierung, cloud-init und **DashyVMToolkit** für Gast-Agent und VirtIO |
| Sicherheit | Passwort, OAuth, Passkeys, Audit-Log, Team-Rechte, UFW, fail2ban, CrowdSec, WireGuard |
| Toolkit | Domains und DNS, Archiv-Import, Datenbank-URLs, Generator für Security-Kommandos, Modrinth für Minecraft |

## Themes

Achtundzwanzig Themes, alle aus vier Farben aufgebaut (Sidebar, Auswahl, Akzent, Hintergrund). Ein Theme lässt sich als Vierfarbcode teilen, sodass ein Team dieselbe Oberfläche mit einem Einfügen reproduziert.

## Generierte Domains

Standardmäßig enthalten generierte Namen über `sslip.io` die Server-IP. Um das zu vermeiden, eine eigene Domain setzen:

```env
DASHY_WILDCARD_DOMAIN=example.com
```

Namen sehen dann aus wie `shop-a1b2c3.example.com` und verraten die Adresse nicht mehr.

## Dokumentation

- [Kompatibilität](../compatibility.md)
- [Entwicklung](../../DEVELOPMENT.md)
- [Mitwirken](../../CONTRIBUTING.md)
- [Sicherheit](../../SECURITY.md)

## Lizenz

Apache-2.0.
