
# FS PBX

## Overview

This project started as a fork of the FusionPBX system but has been extensively redesigned. The front end has been redeveloped using Laravel for the backend and Vue.js for the front end. This new implementation aims to enhance user experience, improve performance, and provide a more modern and maintainable codebase.

## Features

- **Laravel Backend**: Robust and scalable backend infrastructure.
- **Vue.js Front End**: Responsive and interactive user interface.
- **Integration with FusionPBX**: Seamless integration with FusionPBX features.
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

<img width="2392" alt="image" src="https://github.com/user-attachments/assets/2778637b-e5aa-4174-8e8c-0c637847e45e">

<img width="2409" alt="image" src="https://github.com/user-attachments/assets/4bdc239b-4e15-4099-8304-1a179423296d">

<img width="2417" alt="image" src="https://github.com/user-attachments/assets/5c885878-053c-4e4d-800f-7ad4d919894d">


## Prerequisites

Before you begin, ensure you have met the following requirements:

- Debian 11 or 12

## Installation

1. **Download and run the installation script**

   ```bash
    wget -O- https://raw.githubusercontent.com/nemerald-voip/fspbx/main/install/install-fspbx.sh | bash
   ```
   
### Usage
After completing the installation steps, you can access the application at your domain.


## How to update
After pulling the updates, run this command to install them.

   ```bash
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


