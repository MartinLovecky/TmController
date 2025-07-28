# TmController - Advanced Server Controller for TrackMania Nations Forever

> **Status**: In Development

TmController is an upgraded and modified version of the original Xaseco, originally developed by Xymph. This project has undergone extensive modifications, making significant improvements and changes for enhanced performance, modern compatibility, and added features.

> **Note**: This project is maintained by Yuhzel. Documentation will be generated soon using [Doctum](https://doctum.github.io/)
> **Note**: Setting XML files changed into JSON but panels and styles kept as XML 

## Features
- Modernized architecture, fully compatible with PHP 8.3.
- New functionality and refactored code for streamlined performance.
- Retains essential features of old Xaseco.

## Getting Started

### Prerequisites
- [TrackMania Nations Forever Dedicated Server](https://trackmaniaforever.com/download/)
- [PHP 8.3+](https://www.php.net/downloads) 
- Required PHP Extensions (ensure the following extensions are enabled):
   - `curl`
   - `openssl`
   - `pdo_mysql`
- MySQl database setup 

## Installation

### Option 1: Download from Google Drive
You can download the entire folder from Google Drive (link will be provided).

### Option 2: Clone the Repository
Install the dependencies using [Composer](https://getcomposer.org/). To do this, follow these steps:
```bash
   git clone https://github.com/MartinLovecky/TmController
   cd TmController
   composer install
```

### Setup
1. Create a MySQL database and user. If you are unsure how to do this, please refer to tutorials like [MySQL How-Tos](https://docs.digitalocean.com/products/databases/mysql/how-to/) or search for similar resources online.
2. Move the .env file from the /public folder to the root directory. Edit the file to provide the required information for the Database, Karma, and Dedimania.
3. Ensure the TrackMania Nations Forever Dedicated Server and MySQL are set up and running.
4. From TmController folder run
```bash
   php index.php
```
- You can also view result (unformatted) in browser
```bash
   php -S 127.0.0.1:1234
```

### Code Style
Please follow [PSR-12 coding standards](https://www.php-fig.org/psr/psr-12/) and run tests before submitting.

### License
  is released under the [MIT License](https://opensource.org/licenses/MIT).

### Credits
Original Author: Xymph for Xaseco
Current Maintainer: Yuhzel