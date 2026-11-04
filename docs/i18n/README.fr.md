[English](../../README.md) · **Français** · [Español](./README.es.md) · [Deutsch](./README.de.md) · [日本語](./README.ja.md) · [简体中文](./README.zh-cn.md)

<div align="center">

<img src="../assets/dashy-mark.svg" alt="Dashy" width="88" />

# Dashy

**Le plan de contrôle que vous hébergez vous-même.** Applications, bases de données, services, serveurs et machines virtuelles depuis un seul tableau de bord.

</div>

## Présentation

Dashy s'installe une fois et reste chez vous. Il déploie des applications depuis Git, gère des bases de données, installe des services en un clic, administre des serveurs en SSH et crée des machines virtuelles sur Proxmox VE.

Aucun agent n'est nécessaire sur les machines gérées : SSH suffit. Si vous arrêtez Dashy, vos charges de travail continuent de tourner et restent administrables à la main.

## Installation

```bash
curl -fsSL https://raw.githubusercontent.com/limoonsdev/dashy/main/scripts/install.sh | sudo bash
```

L'installeur vérifie l'hôte, installe Docker si nécessaire, construit ou récupère l'image, démarre la pile et affiche l'adresse du tableau de bord. Ouvrez ensuite `http://<serveur>:8000` et créez le premier compte : il devient administrateur de l'instance.

## Fonctionnalités

| Domaine | Détail |
| --- | --- |
| Applications | Git, Dockerfile, Nixpacks, Railpack, docker-compose, préversions par branche, retour arrière immédiat |
| Bases de données | PostgreSQL, MySQL, MariaDB, MongoDB, Redis, KeyDB, ClickHouse, Dragonfly, sauvegardes S3 |
| Services | Catalogue en un clic, déployable sans écrire de fichier compose |
| Serveurs | Ajout par SSH, installation de Docker et du proxy, métriques sans agent |
| Machines virtuelles | Choix de la distribution, dimensionnement automatique, cloud-init, **DashyVMToolkit** pour l'agent invité et les réglages VirtIO |
| Sécurité | Mot de passe, OAuth, passkeys, journal d'audit, contrôle d'accès par équipe, durcissement UFW, fail2ban, CrowdSec, WireGuard |
| Toolkit | Domaines et DNS, import d'archives, URLs de base de données, générateur de commandes de sécurité, Modrinth pour Minecraft |

## Thèmes

Vingt-huit thèmes fournis, tous construits sur quatre couleurs (barre latérale, sélection, accent, fond). Un thème se partage sous forme de code à quatre couleurs, donc une équipe peut reproduire exactement la même interface en un copier-coller.

## Domaines générés

Par défaut les noms générés utilisent `sslip.io`, qui contient l'adresse IP du serveur. Pour l'éviter, définissez un domaine que vous possédez :

```env
DASHY_WILDCARD_DOMAIN=example.com
```

Les hôtes générés ressemblent alors à `boutique-a1b2c3.example.com` et ne révèlent plus l'adresse.

## Documentation

- [Compatibilité](../compatibility.md)
- [Développement](../../DEVELOPMENT.md)
- [Contribution](../../CONTRIBUTING.md)
- [Sécurité](../../SECURITY.md)

## Licence

Apache-2.0.
