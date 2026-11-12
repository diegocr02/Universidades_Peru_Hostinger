# Activación de la contingencia en Hostinger (entrega en GitHub)

**IMPORTANTE: módulo integrado al código, desactivado por defecto. No hay credenciales, respaldos ni receptores activos.**

1. Validar primero en entorno de pruebas: PHP CLI, extensiones `sodium`, `zlib`, `pdo_mysql` y suficiente espacio privado.
2. Generar una clave aleatoria de 32 bytes con `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"` y guardarla fuera del servidor. En `.env` añadir `CNI_BACKUP_KEY=...`, `CNI_BACKUP_ENABLED=true` y `CNI_BACKUP_PANEL_ENABLED=true`.
3. `php artisan config:clear`; probar `php artisan contingencia:crear --solo-local` y verificar en `storage/app/private/contingencia/` que existan `.cni` + `.json`. Nunca subir datos a Git.
4. Probar `php artisan contingencia:descifrar ID /RUTA/PRIVADA/prueba.sql.gz` e importar en una BASE NUEVA VACÍA. Verificar integridad de datos y borrado seguro del SQL en claro.
5. Instalar el receptor desde `docs/contingencia/receptor-externo/` en **otro alojamiento HTTPS**, fuera del servidor principal. Guardar su token y directorio privado fuera de `public_html`.
6. Configurar `CNI_BACKUP_ENDPOINT_A`/`CNI_BACKUP_TOKEN_A` y opcionalmente destino B. Usar tokens diferentes, HTTPS y un almacenamiento independiente.
7. Activar Cron diario ejecutando `php artisan contingencia:crear` desde la raíz del proyecto, y Cron de reintentos `php artisan contingencia:reenviar`. Verificar zona horaria de Hostinger, PHP CLI y logs.
8. Panel: `/administracion/contingencia` para usuario de rol `admin` con nombre de cuenta `Super_Admin`, si habilitado.

**Limitaciones:** solo tablas InnoDB; no adjuntos, vistas, procedimientos ni failover automático. Un respaldo diario tiene RPO de hasta 24 h. Se requiere probar recuperación manual antes de declarar listo para producción. No ejecutar `migrate:fresh`.