<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.html');
    exit();
}

// Database connection

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getConnection();
} catch(Exception $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}

// Get products data
$query = "SELECT * FROM products ORDER BY name ASC";
$products = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);



// Handle product deletion if requested
if (isset($_POST['delete_product']) && isset($_POST['product_id'])) {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    if ($product_id) {
        try {
            // Get product image URL before deletion
            $stmt = $pdo->prepare('SELECT image_url FROM products WHERE product_id = ?');
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            // Delete the product image if it exists
            if ($product && $product['image_url']) {
                $imagePath = '../' . $product['image_url'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            // Delete the product from database
            $stmt = $pdo->prepare('DELETE FROM products WHERE product_id = ?');
            $stmt->execute([$product_id]);

            // Refresh the products list
            $products = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die('Error al eliminar el producto');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - Jersix</title>
    <link rel="stylesheet" href="../Css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="shortcut icon" href="../img/ICON.png" type="image/x-icon">
    <style>
        /* Estilos para el formulario de Agregar Jersey Rápido */
        .quick-add-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .quick-add-form h3 {
            margin-top: 0;
            color: #2c3e50;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            flex: 1;
            min-width: 180px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .file-input-group {
            flex: 2;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        .btn-success {
            background-color: #3498DB;
            color: white;
        }

        .btn-success:hover {
            background-color: #3498DB;
        }

        /* Estilos para mensajes */
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Jersix.mx</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Inicio</a>
                </li>
                <li class="nav-item active">
                    <a href="products.php"><i class="fas fa-shopping-bag"></i> Productos</a>
                </li>
                <li class="nav-item">
                    <a href="orders.php"><i class="fas fa-shopping-cart"></i> Pedidos</a>
                </li>
                <li class="nav-item">
                    <a href="customers.php"><i class="fas fa-users"></i> Clientes</a>
                </li>
                <li class="nav-item">
                    <a href="newsletter.php"><i class="fas fa-envelope"></i> Correos</a>
                </li>
                <li class="nav-item">
                    <a href="csv_generator.php"><i class="fas fa-file-csv"></i> Generador CSV</a>
                </li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Gestión de Productos</h1>
                <div class="admin-controls">
                    <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    <a href="logout.php" class="btn btn-primary">Cerrar Sesión</a>
                </div>
            </div>
            
            <?php if (isset($_SESSION['message'])): ?>
            <div class="message <?php echo $_SESSION['message_type']; ?>">
                <?php 
                    echo htmlspecialchars($_SESSION['message']);
                    // Limpiar el mensaje después de mostrarlo
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                ?>
            </div>
            <?php endif; ?>
            
            <div class="content-actions">
                <a href="add_product.php" class="btn btn-primary"><i class="fas fa-plus"></i> Agregar Producto</a>
                <a href="bulk_product_upload.php" class="btn btn-primary"><i class="fas fa-upload"></i> Carga Masiva</a>
                <a href="csv_generator.php" class="btn btn-primary"><i class="fas fa-file-csv"></i> Generador de CSV</a>
                <button id="showQuickAddForm" class="btn btn-success"><i class="fas fa-bolt"></i> Agregar Jersey Rápido</button>
                <a href="check_permissions.php" class="btn btn-warning"><i class="fas fa-tools"></i> Verificar Permisos</a>
            </div>
            <br>

            <!-- Formulario de Agregar Jersey Rápido (oculto inicialmente) -->
            <div id="quickAddForm" style="display: none;" class="quick-add-form">
                <h3><i class="fas fa-tshirt"></i> Agregar Jersey Rápido</h3>
                <p class="form-info">El nombre se generará automáticamente con el formato: <strong>Equipo Tipo Temporada</strong> (ej: Barcelona Local 24/25)</p>
                <form action="quick_add_jersey.php" method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="equipo">Equipo</label>
                            <input type="text" id="equipo" name="equipo" required placeholder="Ej: Real Madrid, Barcelona, México">
                        </div>
                        <div class="form-group">
                            <label for="tipo">Tipo</label>
                            <select id="tipo" name="tipo" required>
                                <option value="Local">Local</option>
                                <option value="Visitante">Visitante</option>
                                <option value="Tercera">Tercera</option>
                                <option value="Portero">Portero</option>
                                <option value="Auténtica">Auténtica</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="temporada">Temporada</label>
                            <input type="text" id="temporada" name="temporada" value="2023-2024" placeholder="Ej: 2023-2024">
                        </div>
                    </div>
                    
                    <div class="form-group preview-group" style="margin-bottom: 20px; padding: 10px; background-color: #f8f9fa; border-radius: 5px;">
                        <label>Vista previa del nombre:</label>
                        <div id="nombrePreview" style="font-weight: bold; padding: 5px; border: 1px dashed #ccc; border-radius: 3px;">Barcelona Local 24/25</div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="categoria">Categoría</label>
                            <select id="categoria" name="categoria" required>
                                <option value="Equipos">Equipos</option>
                                <option value="Selecciones">Selecciones</option>
                                <option value="Retro">Retro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="precio">Precio</label>
                            <input type="number" id="precio" name="precio" step="0.01" value="899.00" required>
                        </div>
                        <div class="form-group">
                            <label for="stock">Stock</label>
                            <input type="number" id="stock" name="stock" value="10" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group file-input-group">
                            <label for="imagen">Imagen</label>
                            <input type="file" id="imagen" name="imagen" required accept="image/jpeg,image/png,image/webp">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Jersey</button>
                        <button type="button" id="cancelQuickAdd" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</button>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Nombre</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Categoría</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <?php if ($product['image_url']): ?>
                                    <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 50px; height: 50px; object-fit: cover;">
                                <?php else: ?>
                                    <span>No imagen</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td>$<?php echo number_format($product['price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($product['stock']); ?></td>
                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                            <td class="actions">
                                <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-small btn-edit">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este producto?');">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <button type="submit" name="delete_product" class="btn btn-small btn-delete">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>


        </div>
    </div>

    <script>
        // JavaScript para mostrar/ocultar el formulario de Agregar Jersey Rápido
        document.addEventListener('DOMContentLoaded', function() {
            const showFormButton = document.getElementById('showQuickAddForm');
            const cancelButton = document.getElementById('cancelQuickAdd');
            const quickAddForm = document.getElementById('quickAddForm');
            
            showFormButton.addEventListener('click', function() {
                quickAddForm.style.display = 'block';
                showFormButton.style.display = 'none';
                // Hacer scroll al formulario
                quickAddForm.scrollIntoView({ behavior: 'smooth' });
            });
            
            cancelButton.addEventListener('click', function() {
                quickAddForm.style.display = 'none';
                showFormButton.style.display = 'inline-block';
            });

            // Actualizar automáticamente el nombre del producto basado en los campos seleccionados
            const equipoInput = document.getElementById('equipo');
            const tipoSelect = document.getElementById('tipo');
            const temporadaInput = document.getElementById('temporada');
            const categoriaSelect = document.getElementById('categoria');
            const nombrePreview = document.getElementById('nombrePreview');

            // Función para actualizar la vista previa del nombre
            function actualizarNombrePreview() {
                const equipo = equipoInput.value.trim();
                if (!equipo) {
                    nombrePreview.textContent = 'Por favor, ingresa un equipo';
                    return;
                }
                
                const tipo = tipoSelect.value;
                const categoria = categoriaSelect.value;
                const temporada = temporadaInput.value.trim();
                
                // Generar temporada abreviada
                let temporadaAbreviada = '';
                if (temporada && categoria !== 'Retro') {
                    // Extraer los últimos dos dígitos de cada año si el formato es YYYY-YYYY
                    if (temporada.match(/(\d{4})[\/\-](\d{4})/)) {
                        const años = temporada.split(/[\/\-]/);
                        const primerAnio = años[0].substr(2, 2);
                        const segundoAnio = años[1].substr(2, 2);
                        temporadaAbreviada = primerAnio + '/' + segundoAnio;
                    } else {
                        temporadaAbreviada = temporada;
                    }
                }
                
                // Construir el nombre
                let nombre = equipo + ' ' + tipo;
                if (temporadaAbreviada) {
                    nombre += ' ' + temporadaAbreviada;
                }
                
                nombrePreview.textContent = nombre;
            }

            // Función para generar automáticamente una descripción basada en los valores seleccionados
            function actualizarDescripcion() {
                const equipo = equipoInput.value.trim();
                if (!equipo) return;
                
                const tipo = tipoSelect.value;
                const temporada = temporadaInput.value.trim();
                const categoria = categoriaSelect.value;
                
                // Si cambia la categoría a "Retro", ajustar el campo de temporada
                if (categoria === 'Retro' && temporadaInput.value === '2023-2024') {
                    temporadaInput.value = '';
                    temporadaInput.placeholder = 'Opcional para Retro';
                } else if (categoria !== 'Retro' && temporadaInput.value === '') {
                    temporadaInput.value = '2023-2024';
                }
                
                actualizarNombrePreview();
            }

            // Actualizar cuando cambien los valores
            equipoInput.addEventListener('input', actualizarDescripcion);
            tipoSelect.addEventListener('change', actualizarDescripcion);
            temporadaInput.addEventListener('input', actualizarDescripcion);
            categoriaSelect.addEventListener('change', actualizarDescripcion);
            
            // Actualizar la vista previa de inmediato
            actualizarNombrePreview();
        });
    </script>
</body>
</html>