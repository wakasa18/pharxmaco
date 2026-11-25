<?php
foreach (["database.default.database", "app.baseURL", "app.indexPage", "app.forceGlobalSecureRequests", "session.savePath", "cache.file.storePath"] as $key) { $_ENV[$key] = $_SERVER[$key] = getenv($key); }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file("C:/UniServerZ/www/pharxmaco/public" . $path)) return false;
require "C:/UniServerZ/www/pharxmaco/public/index.php";
