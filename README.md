# JerSix - Tienda de Camisetas de Fútbol

JerSix es una plataforma moderna de comercio electrónico especializada en camisetas de fútbol, ofreciendo una amplia selección de camisetas auténticas de equipos de todo el mundo.

## 🚀 Características Principales

- 🛍️ Catálogo completo de camisetas de fútbol
- 🛒 Carrito de compras intuitivo
- 💳 Proceso de pago seguro con integración de MercadoPago
- 📧 Suscripción a newsletter
- 👨‍💼 Panel de administración para gestión de productos y pedidos
- 🎁 Cajas misteriosas para compras sorpresa

## 🛠️ Tecnologías Utilizadas

- Frontend:
  - HTML5
  - CSS3
  - JavaScript
- Backend:
  - PHP
  - MySQL
  - MercadoPago API
  - PHPMailer

## 📋 Requisitos Previos

- PHP 7.4 o superior
- MySQL 5.7 o superior
- Composer
- Servidor web (Apache/Nginx)
- Cuenta de MercadoPago para pagos

## 🚀 Instalación

1. Clona este repositorio:
   ```bash
   git clone https://github.com/tu-usuario/jersix.git
   ```

2. Configura tu servidor web (XAMPP, WAMP, etc.)

3. Importa la base de datos:
   ```bash
   mysql -u root -p < database/jersix.sql
   ```

4. Instala las dependencias:
   ```bash
   composer install
   ```

5. Configura las variables de entorno:
   - Copia `.env.example` a `.env`
   - Configura las credenciales de la base de datos
   - Añade tus credenciales de MercadoPago
   - Configura los ajustes SMTP para correos

## 📁 Estructura del Proyecto

```
jersix/
├── admin/           # Panel de administración
├── Css/            # Archivos de estilos
├── Js/             # Scripts de JavaScript
├── database/       # Archivos SQL
├── img/            # Recursos de imágenes
├── Productos-equipos/  # Páginas de productos
└── vendor/         # Dependencias de Composer
```

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor:

1. Haz un Fork del proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📝 Licencia

Este proyecto está bajo la Licencia MIT - ver el archivo [LICENSE.md](LICENSE.md) para más detalles.

## 📞 Contacto

Para cualquier consulta o sugerencia, no dudes en contactarnos:
- Email: contacto@jersix.com
- Twitter: [@jersix](https://twitter.com/jersix)