[English](../../README.md) · [Français](./README.fr.md) · **Español** · [Deutsch](./README.de.md) · [日本語](./README.ja.md) · [简体中文](./README.zh-cn.md)

<div align="center">

<img src="../assets/dashy-mark.svg" alt="Dashy" width="88" />

# Dashy

**El plano de control que tú alojas.** Aplicaciones, bases de datos, servicios, servidores y máquinas virtuales desde un solo panel.

</div>

## Descripción

Dashy se instala una vez y se queda en tu infraestructura. Despliega aplicaciones desde Git, gestiona bases de datos, instala servicios con un clic, administra servidores por SSH y crea máquinas virtuales en Proxmox VE.

No hace falta ningún agente en los servidores gestionados: con SSH es suficiente. Si dejas de usar Dashy, tus cargas de trabajo siguen funcionando y se pueden administrar a mano.

## Instalación

```bash
curl -fsSL https://raw.githubusercontent.com/limoonsdev/dashy/main/scripts/install.sh | sudo bash
```

El instalador comprueba el host, instala Docker si falta, construye o descarga la imagen, inicia la pila y muestra la dirección del panel. Abre `http://<servidor>:8000` y crea la primera cuenta: será la administradora de la instancia.

## Funciones

| Área | Detalle |
| --- | --- |
| Aplicaciones | Git, Dockerfile, Nixpacks, Railpack, docker-compose, previsualizaciones por rama, vuelta atrás inmediata |
| Bases de datos | PostgreSQL, MySQL, MariaDB, MongoDB, Redis, KeyDB, ClickHouse, Dragonfly, copias a S3 |
| Servicios | Catálogo de un clic, sin escribir un archivo compose |
| Servidores | Alta por SSH, Docker y proxy automáticos, métricas sin agente |
| Máquinas virtuales | Elección de distribución, dimensionado automático, cloud-init y **DashyVMToolkit** para el agente invitado y VirtIO |
| Seguridad | Contraseña, OAuth, passkeys, auditoría, permisos por equipo, UFW, fail2ban, CrowdSec, WireGuard |
| Toolkit | Dominios y DNS, importación de archivos, URLs de bases de datos, generador de comandos de seguridad, Modrinth para Minecraft |

## Temas

Veintiocho temas incluidos, construidos sobre cuatro colores (barra lateral, selección, acento, fondo). Un tema se comparte como un código de cuatro colores, así que un equipo puede reproducir la misma interfaz con un solo pegado.

## Dominios generados

Por defecto los nombres generados usan `sslip.io`, que contiene la IP del servidor. Para evitarlo, define un dominio propio:

```env
DASHY_WILDCARD_DOMAIN=example.com
```

Los nombres pasan a ser `tienda-a1b2c3.example.com` y ya no revelan la dirección.

## Documentación

- [Compatibilidad](../compatibility.md)
- [Desarrollo](../../DEVELOPMENT.md)
- [Contribución](../../CONTRIBUTING.md)
- [Seguridad](../../SECURITY.md)

## Licencia

Apache-2.0.
