<?php
require_once 'db.php';

function obtenerBannerActivo() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM banner_config WHERE activo = 1 ORDER BY fecha_creacion DESC LIMIT 1");
        return $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Error al obtener el banner: " . $e->getMessage());
        return null;
    }
}

$banner = obtenerBannerActivo();
if ($banner): ?>
    <div class="top-banner" style="background-color: <?php echo htmlspecialchars($banner['color_fondo']); ?>; color: <?php echo htmlspecialchars($banner['color_texto']); ?>">
        <div class="banner-content">
            <?php echo htmlspecialchars($banner['mensaje']); ?>
        </div>
    </div>
<?php endif; ?> 