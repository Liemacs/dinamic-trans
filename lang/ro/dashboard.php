<?php

declare(strict_types=1);

/*
 * Every string the shell renders. The sidebar, the page headings and the
 * descriptions under them all read from here via App\Support\DashboardNav, so a
 * new section is one entry in that class plus one block below.
 */
return [
    'brand' => 'Calculator Rute',
    'brand_subtitle' => 'Panou de control',

    'nav' => [
        'overview' => 'Sumar',
        'calculator' => 'Calculator',
        'routes' => 'Rute salvate',
        'report' => 'Raport',
        'fleet' => 'Flotă',
        'vehicles' => 'Vehicule',
        'defaults' => 'Valori implicite',
    ],

    'sections' => [
        'overview' => [
            'title' => 'Sumar',
            'description' => 'Rentabilitatea rutelor calculate până acum.',
        ],
        'calculator' => [
            'title' => 'Calculator rută',
            'description' => 'Introdu datele cursei și vezi costul, salariul, profitul și marja în timp real.',
        ],
        'routes' => [
            'title' => 'Rute salvate',
            'description' => 'Toate cursele calculate, cu profitul și marja fiecăreia.',
        ],
        'report' => [
            'title' => 'Raport',
            'description' => 'Darea de seamă pe o perioadă, gata de tipărit.',
        ],
        'vehicles' => [
            'title' => 'Vehicule',
            'description' => 'Ce costă fiecare camion într-un an, pe categorii.',
        ],
        'defaults' => [
            'title' => 'Valori implicite',
            'description' => 'Cifrele cu care pornește fiecare calcul nou.',
        ],
    ],

    'actions' => [
        'open_menu' => 'Deschide meniul',
        'close_menu' => 'Închide meniul',
        'toggle_section' => 'Comută secțiunea :label',
        'collapse_sidebar' => 'Restrânge meniul',
        'expand_sidebar' => 'Extinde meniul',
        'cancel' => 'Renunță',
        'delete' => 'Șterge',
        'save' => 'Salvează',
        'reset' => 'Resetează',
    ],

    'a11y' => [
        'primary_navigation' => 'Navigare principală',
    ],
];
