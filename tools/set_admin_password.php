<?php
require __DIR__ . '/../vendor/autoload.php';
use App\Kernel;
use App\Entity\User;

['APP_ENV'] = 'dev';
['APP_DEBUG'] = 0;
 = new Kernel(['APP_ENV'], (bool) ['APP_DEBUG']);
->boot();
 = ->getContainer();
 = ->get('doctrine')->getManager();
 = ->getRepository(User::class)->findOneBy(['email' => 'admin@vehicle.local']);
if (psuser) { echo user
