<!-- Improved compatibility of back to top link: See: https://github.com/othneildrew/Best-README-Template/pull/73 -->
<a id="readme-top"></a>

<!-- PROJECT SHIELDS -->
[![Contributors][contributors-shield]][contributors-url]
[![Forks][forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![Issues][issues-shield]][issues-url]
[![GPL-2.0 License][license-shield]][license-url]
[![LinkedIn][linkedin-shield]][linkedin-url]

<!-- PROJECT LOGO -->
<br />
<div align="center">
  <a href="https://github.com/phil-designs/alt-tag-manager">
    <img src="https://phildesigns.com/wp-content/uploads/2025/12/phildesigns-logo.svg" alt="Logo" height="80">
  </a>

  <h3 align="center">Alt Tag Manager</h3>

  <p align="center">
    Find images missing alt tags in your media library and active theme templates. Fix them manually or auto-generate with AI (Anthropic Claude).

Tags: invoices, billing, clients, time-tracking, pdf 
Requires at least: 5.8 
Tested up to: 7.0 
Requires PHP: 7.4 
License: GPL-2.0-or-later
    <br />
    <a href="https://github.com/phil-designs/alt-tag-manager"><strong>Explore the docs »</strong></a>
    <br />
    <br />
    <a href="https://github.com/phil-designs/alt-tag-manager">View Demo</a>
    &middot;
    <a href="https://phildesigns.com/plugin-feedback/">Report Bug</a>
    &middot;
    <a href="https://phildesigns.com/plugin-feedback/">Request Feature</a>
  </p>
</div>

<!-- TABLE OF CONTENTS -->
<details>
  <summary>Table of Contents</summary>
  <ol>
    <li>
      <a href="#about-the-project">About The Project</a>
      <ul>
        <li><a href="#built-with">Built With</a></li>
      </ul>
    </li>
    <li>
      <a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#prerequisites">Prerequisites</a></li>
        <li><a href="#installation">Installation</a></li>
      </ul>
    </li>
    <li><a href="#changelog">Changelog</a></li>
    <li><a href="#license">License</a></li>
    <li><a href="#contact">Contact</a></li>
  </ol>
</details>

<!-- ABOUT THE PROJECT -->
## About The Project

[![Product Name Screen Shot][product-screenshot]](https://github.com/phil-designs/alt-tag-manager)

Find images missing alt tags in your media library and active theme templates. Fix them manually or auto-generate with AI (Anthropic Claude).

**Features**
* Full scan of the media library for missing alt tags
* Full scan of the active theme (parent and child) template files
* AI-powered alt tag generation via Anthropic Claude API
* Bulk AI generation — processes every untagged media image in one pass
* Rate-limit protection — 500ms delay between requests + automatic retry on 429 errors
* Content sync — saving an alt tag rewrites matching `<img>` tags across post content, CPTs, and ACF WYSIWYG fields
* CSV export and import for bulk manual updates


<p align="right">(<a href="#readme-top">back to top</a>)</p>

### Built With

* [![jQuery][JQuery-badge]][JQuery-url]
* [![WordPress][WordPress-badge]][WordPress-url]
* [![PHP][PHP-badge]][PHP-url]


<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- GETTING STARTED -->
## Getting Started

 

### Prerequisites

* WordPress 6.7 or higher
* PHP 7.4 or higher

### Installation

1. Upload `alt-tag-manager` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Media > Alt Tag Settings** and enter your Anthropic API key to enable AI generation.
4. Go to **Media > Search Alt Tags** and scan your media library and active theme for missing alt tags.
5. Add alt tags manually, generate them with AI per image, or run a **Bulk AI Generate** for all missing tags at once.


<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- CONTRIBUTING -->
## Changelog

### 1.2.0
- Added bulk AI generation for all media library images missing alt tags
- Added rate-limit protection with 500ms delay and automatic retry on 429 errors
- Added content sync — alt tag saves now update matching `<img>` tags in post content, CPTs, and ACF WYSIWYG fields
- Added manual bulk updates via CSV import
- Added listener for media library changes

### 1.1.0
- Added parent theme tab and scan support
- Fixed child-theme scanner bug

### 1.0.0
- Initial release


<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- LICENSE -->
## License

Distributed under the GPL-2.0 License. See `LICENSE.txt` for more information.


<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- CONTACT -->
## Contact

Phillip De Vita - [LinkedIn](https://linkedin.com/in/phildesigns) - phil@phildesigns.com

Project Link: [https://github.com/phil-designs/alt-tag-manager](https://github.com/phil-designs/alt-tag-manager)


<p align="right">(<a href="#readme-top">back to top</a>)</p>


<!-- MARKDOWN LINKS & IMAGES -->
[contributors-shield]: https://img.shields.io/github/contributors/phil-designs/alt-tag-manager.svg?style=for-the-badge
[contributors-url]: https://github.com/phil-designs/alt-tag-manager/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/phil-designs/alt-tag-manager.svg?style=for-the-badge
[forks-url]: https://github.com/phil-designs/alt-tag-manager/network/members
[stars-shield]: https://img.shields.io/github/stars/phil-designs/alt-tag-manager.svg?style=for-the-badge
[stars-url]: https://github.com/phil-designs/alt-tag-manager/stargazers
[issues-shield]: https://img.shields.io/github/issues/phil-designs/alt-tag-manager.svg?style=for-the-badge
[issues-url]: https://github.com/phil-designs/alt-tag-manager/issues
[license-shield]: https://img.shields.io/github/license/phil-designs/alt-tag-manager.svg?style=for-the-badge
[license-url]: https://github.com/phil-designs/alt-tag-manager/blob/master/LICENSE.txt
[linkedin-shield]: https://img.shields.io/badge/-LinkedIn-black.svg?style=for-the-badge&logo=linkedin&colorB=555
[linkedin-url]: https://linkedin.com/in/phildesigns/
[product-screenshot]: https://phildesigns.com/wp-content/uploads/2026/08/advertisement-alt-tag-manager-software-feature-various-optimize.png
[JQuery-badge]: https://img.shields.io/badge/jQuery-0769AD?style=for-the-badge&logo=jquery&logoColor=white
[JQuery-url]: https://jquery.com
[WordPress-badge]: https://img.shields.io/badge/WordPress-21759B?style=for-the-badge&logo=wordpress&logoColor=white
[WordPress-url]: https://wordpress.org
[PHP-badge]: https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white
[PHP-url]: https://www.php.net/
