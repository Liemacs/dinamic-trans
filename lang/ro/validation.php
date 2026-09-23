<?php

declare(strict_types=1);

/*
 * The validation rules this application actually uses, in Romanian.
 *
 * Only these are translated; APP_FALLBACK_LOCALE is `en`, so a rule not listed
 * here still produces a readable English message rather than the raw key.
 *
 * Every message is phrased so that :attribute never has to agree with an
 * adjective. Romanian marks gender on those — "distanța este obligatorie" but
 * "cursul este obligatoriu" — and a placeholder cannot carry the ending, so the
 * wording sidesteps it instead of getting it wrong half the time. The field names
 * come from the `attributes:` argument of each validate() call.
 */
return [
    'required' => 'Completează :attribute.',
    'string' => 'Completează :attribute cu text.',
    'boolean' => 'Valoarea pentru :attribute trebuie să fie adevărat sau fals.',
    'numeric' => 'Completează :attribute cu un număr.',
    'integer' => 'Completează :attribute cu un număr întreg.',
    'exists' => 'Opțiunea aleasă pentru :attribute nu mai există.',

    'min' => [
        'numeric' => 'Valoarea minimă pentru :attribute este :min.',
        'string' => 'Câmpul :attribute trebuie să aibă cel puțin :min caractere.',
    ],

    'max' => [
        'numeric' => 'Valoarea maximă pentru :attribute este :max.',
        'string' => 'Câmpul :attribute nu poate depăși :max caractere.',
    ],

    'attributes' => [],
];
