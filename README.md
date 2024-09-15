# reCAPTCHA Content Block for WordPress

## Description
**reCAPTCHA Content Block** is a WordPress plugin that allows you to protect specific content within your website by requiring users to complete a Google reCAPTCHA verification before displaying the content. It’s ideal for hiding sensitive information like email addresses, documents, or any other content from bots and spammers.

This plugin integrates seamlessly with the Gutenberg block editor, allowing you to easily add a protected content block anywhere on your WordPress site. Once the reCAPTCHA challenge is completed successfully, the hidden content is revealed.

## Features
- **Simple integration with Gutenberg**: Add a reCAPTCHA-protected block directly from the block editor.
- **Protect sensitive information**: Ideal for hiding email addresses, phone numbers, or links until the user has verified they are human.
- **Customizable button text**: You can now modify the button text that appears before revealing the protected content.
- **Support for multiple block types**: In addition to text, you can include images, videos, galleries, or any other Gutenberg block inside the protected area.
- **Google reCAPTCHA v2 support**: Utilizes the Google reCAPTCHA v2 checkbox to ensure human verification.

## How to Use
1. Install and activate the plugin.
2. Go to **Settings > reCAPTCHA Content Block** and enter your Google reCAPTCHA API keys (Site key and Secret key).
3. In the Gutenberg editor, search for the **reCAPTCHA Content Block**, add it to your page, and insert the content you want to protect.
4. Customize the button text and add any other blocks inside the protected area.

## Installation
1. Clone or download this repository.
2. Upload the plugin to your WordPress `wp-content/plugins` directory.
3. Activate the plugin in the WordPress dashboard.
4. Configure the reCAPTCHA keys in the plugin settings.

## Requirements
- WordPress 5.0 or higher (supports the Gutenberg block editor)
- Google reCAPTCHA v2 (API keys required)

## Contributing
Feel free to open issues for bugs, improvements, or feature requests. Pull requests are welcome!

## License
This plugin is licensed under the [MIT License](LICENSE).
