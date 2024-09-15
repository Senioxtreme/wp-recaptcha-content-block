# reCAPTCHA Content Block for WordPress

## Description
**reCAPTCHA Content Block** is a WordPress plugin that allows you to protect specific content within your website by requiring users to complete a Google reCAPTCHA verification before displaying the content. It’s particularly useful for hiding public email addresses or other sensitive information from bots and spammers.

This plugin integrates seamlessly with the Gutenberg block editor, allowing you to easily add a protected content block anywhere on your WordPress site. Once the reCAPTCHA challenge is completed successfully, the hidden content is revealed.

## Features
- **Simple integration with Gutenberg:** Add a reCAPTCHA-protected block directly from the block editor.
- **Protect sensitive information:** Ideal for hiding email addresses, phone numbers, or links from bots.
- **Easy to use:** No coding skills required. Just insert the block, add your content, and configure reCAPTCHA keys.
- **Customizable:** Protect any type of content that requires user verification.
- **reCAPTCHA v2 support:** Utilizes the Google reCAPTCHA v2 checkbox to ensure human verification.

## How to Use
1. Install and activate the plugin.
2. Go to the **Settings > reCAPTCHA Content Block** menu and enter your Google reCAPTCHA site and secret keys.
3. In the Gutenberg editor, search for the **reCAPTCHA Content Block**, add it to your page, and insert the content you want to protect.
4. The content will remain hidden until the user completes the reCAPTCHA challenge on the frontend.

## Installation
1. Clone or download the repository.
2. Upload the plugin to your WordPress `wp-content/plugins` directory.
3. Activate the plugin in the WordPress dashboard.
4. Configure the reCAPTCHA keys in the plugin settings.

## Requirements
- WordPress 5.0 or higher (supports the Gutenberg editor)
- Google reCAPTCHA v2 (keys are required)

## Contributing
Feel free to open issues for bugs, improvements, or feature requests. Pull requests are also welcome!

## License
This plugin is licensed under the [MIT License](LICENSE).
