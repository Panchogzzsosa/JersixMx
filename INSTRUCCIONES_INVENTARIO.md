# Sistema de Gestión de Inventario - Jersix

## Configuración Inicial

### 1. Crear la tabla de inventario
Antes de usar el sistema de inventario, necesitas ejecutar el script de configuración:

1. Ve al panel de administración
2. Navega a: `http://tu-dominio/admin2/create_inventory_table.php`
3. Este script creará la tabla `product_inventory` y poblará datos iniciales

### 2. Acceder al sistema de inventario
Una vez configurado, puedes acceder al sistema de inventario desde:
- Panel principal → Menú lateral → "Inventario"
- O directamente en: `http://tu-dominio/admin2/inventario.php`

## Funcionalidades

### Estadísticas del Inventario
- **Total Productos**: Número total de productos en el catálogo
- **Stock Bajo**: Productos con 5 unidades o menos
- **Sin Stock**: Productos completamente agotados
- **Total Unidades**: Suma de todas las unidades en inventario

### Gestión por Tallas
Para cada producto puedes gestionar el stock por tallas:
- **S**: Talla pequeña
- **M**: Talla mediana
- **L**: Talla grande
- **XL**: Talla extra grande

### Actualización de Stock
1. Encuentra el producto en la tabla
2. Modifica los números en los campos de cada talla
3. Los cambios se guardan automáticamente al cambiar el valor
4. El stock total se actualiza automáticamente

### Indicadores Visuales
- **Verde**: Stock alto (>10 unidades)
- **Amarillo**: Stock medio (6-10 unidades)
- **Rojo**: Stock bajo (1-5 unidades)
- **Gris**: Sin stock (0 unidades)

## Estructura de la Base de Datos

### Tabla `product_inventory`
```sql
CREATE TABLE `product_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `size` varchar(10) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_size_unique` (`product_id`, `size`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
);
```

### Relación con la tabla `products`
- La tabla `product_inventory` está relacionada con `products` mediante `product_id`
- El campo `stock` en `products` se actualiza automáticamente con la suma de todas las tallas
- Solo se incluyen productos que NO son "Gift Card"

## Características Técnicas

### Actualización Automática
- Los formularios se envían automáticamente al cambiar un valor
- No es necesario hacer clic en "Guardar"
- El stock total se recalcula automáticamente

### Validación
- Solo se permiten valores numéricos positivos
- Los valores mínimos son 0
- Se valida la existencia del producto antes de actualizar

### Seguridad
- Verificación de sesión de administrador
- Sanitización de datos de entrada
- Prevención de inyección SQL mediante prepared statements

## Notas Importantes

1. **Productos Gift Card**: No aparecen en el inventario ya que no tienen tallas
2. **Eliminación de Productos**: Si eliminas un producto, su inventario se elimina automáticamente
3. **Backup**: Se recomienda hacer backup de la tabla `product_inventory` regularmente
4. **Rendimiento**: Para tiendas con muchos productos, considera implementar paginación

## Solución de Problemas

### Si no aparecen productos:
1. Verifica que la tabla `product_inventory` existe
2. Ejecuta nuevamente `create_inventory_table.php`
3. Verifica que los productos no sean "Gift Card"

### Si no se actualizan los valores:
1. Verifica la conexión a la base de datos
2. Revisa los logs de error de PHP
3. Verifica que tienes permisos de escritura en la base de datos

### Si hay errores de SQL:
1. Verifica que la tabla `products` existe y tiene la estructura correcta
2. Asegúrate de que los `product_id` sean válidos
3. Verifica que la base de datos soporte FOREIGN KEY constraints 