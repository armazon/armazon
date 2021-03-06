# ARMAZÓN

[![Build Status](https://travis-ci.org/armazon/armazon.svg?branch=master)](https://travis-ci.org/armazon/armazon) 
[![Code Climate](https://codeclimate.com/github/armazon/armazon/badges/gpa.svg)](https://codeclimate.com/github/armazon/armazon) 
[![Test Coverage](https://codeclimate.com/github/armazon/armazon/badges/coverage.svg)](https://codeclimate.com/github/armazon/armazon/coverage) 
[![Latest Stable Version](https://poser.pugx.org/armazon/armazon/v/stable)](https://packagist.org/packages/armazon/armazon) 
[![Latest Unstable Version](https://poser.pugx.org/armazon/armazon/v/unstable)](https://packagist.org/packages/armazon/armazon) 
[![Total Downloads](https://poser.pugx.org/armazon/armazon/downloads)](https://packagist.org/packages/armazon/armazon) 
[![License](https://poser.pugx.org/armazon/armazon/license)](https://packagist.org/packages/armazon/armazon) 

**Marco de Trabajo y Servidor de Aplicación PHP de Alto Rendimiento y Flexibilidad.**

Este proyecto fue desarrollado orgullosamente en **español**. 

## Inicio rápido

Para iniciar una aplicación web con ARMAZÓN solo debes ejecutar lo siguiente en la consola:

```shell
composer create-project armazon/proyecto-base dir-ejemplo
cd dir-ejemplo
php armazon
```

## Requerimientos

Para ejecutar tu nueva aplicación puedes usar PHP-FPM (usar publico/arranque.php) o el servidor de aplicación de ARMAZÓN.

Para usar el servidor de aplicación de ARMAZÓN deberás cumplir con los siguientes requerimientos:

- PHP 5.5 o mayor.
- Extensión Swoole 1.8 o mayor.
- Extensión POSIX.
- Extensión PDO (solo en caso de usar modelos relacionales).
- Extensión Mbstring.
- Sistema operativo basado en linux.

## Intrucciones para instalar requerimientos (ubuntu)

**PHP 7**
```shell
sudo apt-get update
sudo apt-get install python-software-properties
sudo apt-get install language-pack-en-base
sudo LC_ALL=en_US.UTF-8 add-apt-repository ppa:ondrej/php
sudo apt-get update
sudo apt-get install php7.0-cli php7.0-curl php7.0-dev php7.0-fpm php7.0-gd php7.0-mysql php7.0-opcache php-pear
service php7.0-fpm stop
```

**Swoole**
```shell
sudo apt-get install libcurl4-openssl-dev build-essential
sudo pecl install swoole
sudo echo "extension=swoole.so" > /etc/php/mods-available/swoole.ini
sudo ln -s /etc/php/mods-available/swoole.ini /etc/php/7.0/cli/conf.d/20-swoole.ini
```

## Versionado

Por transparencia en nuestro ciclo de liberación y en el esfuerzo por mantener la compatibilidad con versiones anteriores, este proyecto se mantiene bajo [las directrices de Semántica de Versionado] (http://semver.org/). Nos adherimos a estas normas siempre que sea posible, aunque a veces podemos meter la pata.

## ¿Qué falta?

- Documentación.
- Pruebas de rendimiento contra otros marcos de trabajo. [Pruebas sin comparación] (https://github.com/armazon/armazon/wiki/Pruebas-de-Rendimiento-(v1)).
- Pruebas unitarias que cubran el 100% del código.
- Implementación de middlewares (viene en camino).
- Componentes básicos como el manejador de sesiones, ODMs, autenticación, etc (mientras tanto diviértete con Composer).
- Corregir errores tanto actuales como futuros.
- Crear comunidad de colaboradores que sientan orgullo de ser latinos.
