# Imagony
[![Packagist](https://img.shields.io/packagist/v/hermajan/imagony.svg)](https://packagist.org/packages/hermajan/imagony)

<p id="readme-perex">Symfony bundle and Twig extension for creating thumbnails of images using <a href="https://imagine.readthedocs.io/">Imagine</a>.</p>

## ✨ Features
*   **Thumbnails:** the Twig function `thumbnail()` creates cached thumbnails of a given size or of a named template.
*   **Resize modes:** fit, shrink only, stretch, fill and exact (crop).
*   **WebP and AVIF:** thumbnails are also converted to modern formats.
*   **Built-in placeholder:** a missing or broken image is replaced by a fallback served by the bundle, no assets to install.
*   **Zero configuration:** works without a config file, GD or Imagick is used automatically.
*   **Diagnostics:** `imagony:check` explains missing PHP extensions and wrong configuration, `imagony:clean` removes generated thumbnails.
*   **Open Graph images:** converts images to the 1.91:1 ratio.

## 📥 Installation
Install this via [Composer](https://getcomposer.org):
```bash
composer require hermajan/imagony
```

PHP 8.2+ with the [GD](https://www.php.net/manual/en/book.image.php) or [Imagick](https://www.php.net/manual/en/book.imagick.php) extension is required.

## 📚 Documentation
Usage and documentation for this project is in [wiki](https://github.com/hermajan/imagony/wiki).
