
# FS PBX

## Overview

This project started as a fork of the FusionPBX system but has been extensively redesigned. The front end has been redeveloped using Laravel for the backend and Vue.js for the front end. This new implementation aims to enhance user experience, improve performance, and provide a more modern and maintainable codebase.

## Features

- **Laravel Backend**: Robust and scalable backend infrastructure.
- **Vue.js Front End**: Responsive and interactive user interface.
- **Integration with FusionPBX**: Integration with remaining FusionPBX features.
- **Tailwind CSS**: Modern and utility-first CSS framework for styling.
- **Modular Design**: Easy to extend and maintain.

## Video Installation tutorial in 10 minutes

[https://youtu.be/7v8sepsqnH4](https://youtu.be/go6dUce0Nis)

[![VIDEO WALKTHOUGH](https://img.youtube.com/vi/go6dUce0Nis/0.jpg)](https://www.youtube.com/watch?v=go6dUce0Nis)


## Screenshots
<img width="2365" alt="image" src="https://github.com/user-attachments/assets/66921621-ab47-4457-ab11-14888c6419ae">

<img width="2409" alt="image" src="https://github.com/user-attachments/assets/6bcd653e-da7a-4de0-9ab6-18a5de02f8c8">

<img width="2401" alt="image" src="https://github.com/user-attachments/assets/18159468-9d74-42ec-b2db-e7cc35bf0162">

<img width="2390" alt="image" src="https://github.com/user-attachments/assets/c5f1265a-147b-4dfe-a85b-bf4541c46ead">

<img width="1600" alt="image" src="https://github.com/user-attachments/assets/89f22edc-ccad-4002-a978-f0fae63f9186">

<img width="1600" alt="image" src="https://github.com/user-attachments/assets/fe3a5405-9f4b-4452-ac12-223cf4c92831">

![Image](https://github.com/user-attachments/assets/507dfa97-0264-480d-a186-767f1bcf7da8)

![Image](https://github.com/user-attachments/assets/c1dcb6da-1a17-44b5-8bc3-cde49faeca07)

<img width="2417" alt="image" src="https://github.com/user-attachments/assets/5c885878-053c-4e4d-800f-7ad4d919894d">


## Prerequisites

Before you begin, ensure you have met the following requirements:

- Debian 11 or 12
- 4GB of RAM (for production, more RAM is recommended)
- 30 GB Hard drive ( for production, an NVME hard drive with more space is recommended)

## Installation

1. **Download and run the installation script**

   ```bash
    wget -O- https://raw.githubusercontent.com/nemerald-voip/fspbx/main/install/install-fspbx.sh | bash
   ```

2. **Configure a custom domain and Let's Encrypt certificate (OPTIONAL)**

    Follow the steps in this article to configure your custom domain.
   
    [How to Secure FS PBX with a Let’s Encrypt SSL Certificate](https://github.com/nemerald-voip/fspbx/wiki/How-to-Secure-FS-PBX-with-a-Let%E2%80%99s-Encrypt-SSL-Certificate)
   
### Usage
After completing the installation steps, you can access the application at your domain.


## How to update
Pull new updates and install them by running these commands:

   ```bash
   cd /var/www/fspbx
   git pull
   php artisan app:update
   ```
Check if there are any pending database updates.

   ```bash
   php artisan migrate:status
   ```
If you find any updates pending, run this command to install them. 
   ```bash
   php artisan migrate
   ```

## Premium Modules
Unlock the full potential of your PBX with our two exciting premium modules designed to take your system to the next level:

**Contact Center Module**: Elevate your call management with an elegant live dashboard and a powerful management portal, ensuring every queue is optimized and easy to control.

**STIR/SHAKEN Module**: Ensure call authenticity with the STIR/SHAKEN module, giving you the power to sign all your calls with Attestation A using your very own certificate.

Experience enhanced functionality and seamless control like never before!

## Contact
For any questions or feedback, please contact us for support.


