=== Discount Tools ===
Contributors: hugoshih
Tags: woocommerce, discount, pricing, dynamic pricing, bulk discount
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Create powerful discount rules for WooCommerce with conditions, priorities, and multiple discount types.

== Description ==

**Discount Tools** is a comprehensive discount management plugin for WooCommerce that empowers you to create flexible pricing rules and sophisticated discount strategies for your online store.

= 🎯 Key Features =

**Multiple Discount Types**
* Percentage discounts (e.g., 20% off)
* Fixed amount discounts (e.g., $10 off)
* Price override (set specific sale price)
* Free shipping discounts
* BOGO deals (coming soon)

**Flexible Conditions**
* Product-based: Specific products, categories, tags
* Cart-based: Cart total, quantity, contains product/category
* User-based: User role, logged-in status, email, order history
* Date & Time: Date ranges, day of week, time of day
* Shipping: Country, state, zone

**Priority System**
* Set rule priority levels
* Control discount stacking
* Apply best discount automatically

**Usage Controls**
* Set maximum usage limits
* Track discount usage
* Schedule automatic activation

**Admin Interface**
* Intuitive tabbed editor
* Visual condition builder with AND/OR logic
* Bulk operations (enable/disable/delete)
* Quick toggle for rule status
* Search and filter rules
* Settings import/export

**Frontend Display**
* Three display styles (Table, Badge, Text)
* Product page discount tables
* Shopping cart discount display
* Checkout savings summary
* Fully responsive design
* Customizable messages

**Performance & Security**
* Built-in caching system
* SQL injection protection
* XSS protection
* Efficient condition evaluation
* Debug mode for troubleshooting

= 📋 Perfect For =

* **E-commerce Stores**: Dynamic pricing strategies
* **Wholesale Businesses**: Quantity-based tiered pricing
* **Membership Sites**: Role-based member discounts
* **Seasonal Sales**: Automated date-based promotions
* **B2B Stores**: Custom pricing for customer groups
* **Clearance Sales**: Category-wide discount campaigns

= 🚀 Use Cases =

**Member Discounts**
Give 15% off to logged-in members automatically

**Bulk Purchase Incentives**
Offer 20% off when customers buy 10+ items

**Free Shipping Promotions**
Provide free shipping on orders over $100

**Weekend Sales**
Run automatic 25% off sales every weekend

**Category Clearance**
Apply 50% off to specific product categories

**First-Time Customer Offers**
Give $10 off to new customers on their first order

**VIP Customer Pricing**
Set special prices for VIP customer roles

**Flash Sales**
Run time-limited sales with automatic activation

= 🎨 Display Options =

**Table Style**
Traditional table showing quantity tiers and price breaks

**Badge Style**
Eye-catching gradient badges with discount amounts

**Text Style**
Simple, clean text format for minimal design

All styles are fully responsive and customizable!

= �� Why Choose Discount Tools? =

* **Easy to Use**: Intuitive interface, no coding required
* **Powerful**: Advanced conditions and logic
* **Flexible**: Unlimited discount rules and combinations
* **Fast**: Optimized for performance with caching
* **Secure**: Protection against common vulnerabilities
* **Compatible**: Works with popular themes and plugins
* **Support**: Comprehensive documentation and support

= 🔗 Links =

* [Documentation](https://docs.example.com/discount-tools)
* [Support Forum](https://wordpress.org/support/plugin/discount-tools)
* [GitHub Repository](https://github.com/yourusername/discount-tools)

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to **Plugins > Add New**
3. Search for "Discount Tools for WooCommerce"
4. Click "Install Now" and then "Activate"
5. Go to **WooCommerce > Discount Tools** to start creating rules

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Navigate to **Plugins > Add New > Upload Plugin**
4. Click "Choose File" and select the ZIP file
5. Click "Install Now" and then "Activate"
6. Go to **WooCommerce > Discount Tools** to configure

= First Time Setup =

1. After activation, go to **WooCommerce > Discount Tools**
2. (Optional) Visit **Settings** to configure global options
3. Click **Add New** to create your first discount rule
4. Fill in rule details (name, type, status, priority)
5. Add conditions to specify when discount applies
6. Set discount type and value
7. Configure display options
8. Click **Publish** to activate the rule

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes, WooCommerce must be installed and activated for this plugin to work.

= Can I stack multiple discounts? =

Yes! Enable "Stack Multiple Discounts" in Settings. Otherwise, only the best discount will apply.

= How do I test discounts without making them live? =

Set the rule status to "Inactive" and use the Preview tab to test calculations.

= Can I schedule discounts to start and end automatically? =

Yes! Set the date range in the General tab and set status to "Scheduled".

= Do discounts work with WooCommerce coupons? =

Yes, discounts work independently from WooCommerce coupons and can be stacked with them.

= Can I create role-based pricing? =

Yes! Use the "User Role" condition to create discounts for specific user roles.

= How do I create quantity-based discounts? =

Use the "Quantity" condition with "Greater Than or Equal" operator (e.g., Quantity >= 10).

= Will this slow down my site? =

No. The plugin uses caching and efficient queries to minimize performance impact.

= Is my data safe? =

Yes. All database queries use prepared statements to prevent SQL injection, and all output is properly escaped.

= Can I import/export settings? =

Yes! Go to Settings and use the Import/Export feature to backup or migrate your configuration.

= What happens to discounts when I deactivate the plugin? =

Discounts will stop applying, but your rules and data are preserved. They'll work again when you reactivate.

= What happens to my data if I uninstall the plugin? =

By default, all data is deleted on uninstall. You can disable this in Settings > Advanced > Delete Data on Uninstall.

= How do I get support? =

Visit the [Support Forum](https://wordpress.org/support/plugin/discount-tools) or check our [Documentation](https://docs.example.com/discount-tools).

== Screenshots ==

1. **Rules List** - Manage all your discount rules in one place
2. **Rule Editor - General Tab** - Configure basic rule settings
3. **Rule Editor - Conditions Tab** - Visual condition builder with AND/OR logic
4. **Rule Editor - Discounts Tab** - Set discount type and value
5. **Rule Editor - Display Tab** - Customize how discounts are displayed
6. **Product Page Display** - Three display styles (Table, Badge, Text)
7. **Shopping Cart Display** - Show applied discounts in cart
8. **Checkout Display** - Display total savings on checkout
9. **Settings Page** - Configure global plugin options
10. **Mobile Responsive** - Fully responsive on all devices

== Changelog ==

= 1.1.1 - 2026-04-15 =

* 修正「使用限制 > 目前使用量」在部分結帳流程不會累加的問題。
* 新增訂單追蹤防重複機制，避免同一筆訂單重複計數。
* 將 Bundle / BXGY Any 的規則識別資料寫入訂單明細，改善商品型折扣統計準確度。
* 修正舊 session 汙染導致規則誤計（例如 KHSC x Dolphin Event）問題。
* 優化從訂單資料回推命中規則的邏輯（含費用項目、贈品項目與套裝價格判斷）。

= 1.1.0 - 2026-04-04 =

* 修正 Bundle 與 BXGY Any 互相遮蔽。
* 修正購物車上方提示在有百分比折扣時漏顯示的問題。
* 精簡購物車計算流程，減少重算負擔。
* Fixed BXGY any rules so all selected gift products are added to the cart.
* Prepared a clean production release package.

= 1.0.6 - 2025-11-29 =
* Enhanced export/import functionality: Now includes discount rules in addition to settings
* Added internationalization support for multiple languages
* Added Traditional Chinese translation (zh_TW) - shared by Taiwan, Hong Kong, Macau, Singapore
* Added Simplified Chinese translation (zh_CN)
* Added English (US) translation (en_US)
* Added English (UK) translation (en_GB)
* Improved custom messages display with SVG icon support
* Removed table display style (Badge and Text styles only)
* Added customizable message text color and font size settings
* Bug fixes and performance improvements

= 1.0.0 - 2025-10-15 =
* Initial release
* Multiple discount types: Percentage, Fixed Amount, Price Override, Free Shipping
* Flexible conditions: Product, Cart, User, Date, Shipping
* Priority system with stackable/non-stackable options
* Usage limits and tracking
* Intuitive admin interface with tabbed editor
* Visual condition builder
* Three frontend display styles
* Shopping cart and checkout display
* Settings import/export
* Caching system for performance
* Security: SQL injection and XSS protection
* Compatibility: WordPress 5.8+, WooCommerce 5.0+, PHP 7.4+

== Upgrade Notice ==

= 1.1.1 =
This update is strongly recommended. It fixes usage counting reliability for percentage, BXGY, and bundle rules across checkout flows.

= 1.0.7 =
BXGY any rules now add every selected gift product automatically. This release is recommended for production use.

= 1.0.6 =
Major update with export/import improvements and internationalization support. Now available in Traditional Chinese, Simplified Chinese, and English!

= 1.0.0 =
Initial release of Discount Tools for WooCommerce. Install to start creating powerful discount rules!

== Additional Information ==

= Requirements =

* WordPress 5.8 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher

= Support =

For support, please visit:
* [Support Forum](https://wordpress.org/support/plugin/discount-tools)
* [Documentation](https://docs.example.com/discount-tools)
* [GitHub Issues](https://github.com/yourusername/discount-tools/issues)

= Contributing =

This plugin is open source! We welcome contributions on [GitHub](https://github.com/yourusername/discount-tools).

= Privacy =

This plugin does not collect or send any user data externally. All data is stored locally in your WordPress database.

= Credits =

Developed with ❤️ for the WordPress and WooCommerce community.
