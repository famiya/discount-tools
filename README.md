# Discount Tools for WooCommerce

A powerful and flexible discount management plugin for WooCommerce that allows you to create complex discount rules with conditions, priorities, and multiple discount types.

## 🎯 Features

### Core Features
- **Multiple Discount Types**: Percentage, Fixed Amount, Price Override, BOGO, BXGY, Free Shipping
- **Flexible Conditions**: Product, Category, User Role, Quantity, Cart Total, Date Range
- **Priority System**: Control which discounts apply first with priority levels
- **Stackable Discounts**: Allow multiple discounts to stack or apply only the best one
- **Usage Limits**: Set maximum usage counts per rule
- **Date Scheduling**: Activate discounts automatically based on date ranges

### Admin Features
- **Intuitive Rule Editor**: Tabbed interface with General, Conditions, Discounts, Display, and Preview tabs
- **Condition Builder**: Visual condition builder with AND/OR logic support
- **Bulk Operations**: Enable/disable, delete multiple rules at once
- **Quick Actions**: Toggle rule status with AJAX
- **Search & Filter**: Find rules quickly with powerful filtering options
- **Settings Management**: Import/export settings, restore defaults

### Frontend Features
- **Product Page Display**: Show discount tables with three styles (Table, Badge, Text)
- **Cart Display**: Show applied discounts in shopping cart
- **Checkout Display**: Display total savings on checkout page
- **Responsive Design**: Fully responsive on all devices
- **Customizable Messages**: Customize discount messages and styling

### Performance & Security
- **Caching System**: Built-in caching for optimal performance
- **SQL Injection Protection**: All queries use prepared statements
- **XSS Protection**: All output is properly escaped
- **Efficient Evaluation**: Smart condition evaluation with short-circuit logic

## 📋 Requirements

- **WordPress**: 5.8 or higher
- **WooCommerce**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher

## 🚀 Installation

### From WordPress Admin
1. Download the plugin ZIP file
2. Go to **Plugins > Add New** in WordPress admin
3. Click **Upload Plugin** and choose the ZIP file
4. Click **Install Now**
5. Activate the plugin

### Manual Installation
1. Upload the `discount-tools` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **WooCommerce > Discount Tools** to configure

## 📖 Quick Start Guide

### Creating Your First Discount

1. Navigate to **WooCommerce > Discount Tools > Add New**
2. **General Tab**: Enter rule name, type, status, priority
3. **Conditions Tab**: Add conditions (product, category, user, etc.)
4. **Discounts Tab**: Select discount type and value
5. **Display Tab**: Configure display options
6. Click **Publish**

For detailed documentation, see [User Guide](docs/USER-GUIDE.md).

## 🏗️ Architecture

See [ARCHITECTURE.md](docs/ARCHITECTURE.md) for detailed technical architecture.

## 🔧 Development

### Setup
\`\`\`bash
git clone https://github.com/famiya/discount-tools.git
cd discount-tools
composer install --dev
npm install
\`\`\`

### Coding Standards
\`\`\`bash
composer run-script phpcs    # Check PHP
composer run-script phpcbf   # Fix PHP
npm run lint:js              # Check JS
\`\`\`

### Testing
\`\`\`bash
composer run-script test     # Unit tests
\`\`\`

## 📄 License

GPL v2 or later. See [LICENSE](LICENSE) for details.

## 📞 Support

- **Documentation**: [docs/](docs/)
- **Issues**: [GitHub Issues](https://github.com/famiya/discount-tools/issues)
- **Support Forum**: [WordPress.org](https://wordpress.org/support/plugin/discount-tools)

---

**Made with ❤️ for WooCommerce**
