<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'accepted' => ':attribute-kenttä on hyväksyttävä.',
    'accepted_if' => ':attribute-kenttä on hyväksyttävä, kun :other on :value.',
    'active_url' => ':attribute-kentän on oltava kelvollinen URL.',
    'after' => ':attribute-kentän on oltava päivämäärä, joka on jälkeen :date.',
    'after_or_equal' => ':attribute-kentän on oltava päivämäärä, joka on sama tai jälkeen :date.',
    'alpha' => ':attribute-kenttä saa sisältää vain kirjaimia.',
    'alpha_dash' => ':attribute-kenttä saa sisältää vain kirjaimia, numeroita, viivoja ja alaviivoja.',
    'alpha_num' => ':attribute-kenttä saa sisältää vain kirjaimia ja numeroita.',
    'array' => ':attribute-kentän on oltava taulukko.',
    'before' => ':attribute-kentän on oltava päivämäärä ennen :date.',
    'before_or_equal' => ':attribute-kentän on oltava päivämäärä sama tai ennen :date.',
    'between' => [
        'array' => ':attribute-kentän on oltava välillä :min - :max kohdetta.',
        'file' => ':attribute-kentän tiedoston on oltava :min - :max kilotavua.',
        'numeric' => ':attribute-kentän on oltava välillä :min - :max.',
        'string' => ':attribute-kentän merkkien on oltava :min - :max.',
    ],
    'boolean' => ':attribute-kentän on oltava true tai false.',
    'confirmed' => ':attribute-kentän vahvistus ei täsmää.',
    'current_password' => 'Salasana on virheellinen.',
    'date' => ':attribute-kentän on oltava kelvollinen päivämäärä.',
    'date_equals' => ':attribute-kentän on oltava päivämäärä, joka on sama kuin :date.',
    'date_format' => ':attribute-kentän on oltava muodossa :format.',
    'different' => ':attribute-kentän ja :other on oltava erilaiset.',
    'digits' => ':attribute-kentän on oltava :digits numeroa.',
    'digits_between' => ':attribute-kentän on oltava :min - :max numeroa.',
    'dimensions' => ':attribute-kentän kuvan mitat ovat virheelliset.',
    'distinct' => ':attribute-kentällä on duplikaattiarvo.',
    'email' => ':attribute-kentän on oltava kelvollinen sähköpostiosoite.',
    'ends_with' => ':attribute-kentän on päätyttävä johonkin seuraavista: :values.',
    'exists' => 'Valittu :attribute ei ole kelvollinen.',
    'file' => ':attribute-kentän on oltava tiedosto.',
    'filled' => ':attribute-kentällä on oltava arvo.',
    'gt' => [
        'array' => ':attribute-kentällä on oltava enemmän kuin :value kohdetta.',
        'file' => ':attribute-kentän tiedoston on oltava suurempi kuin :value kilotavua.',
        'numeric' => ':attribute-kentän on oltava suurempi kuin :value.',
        'string' => ':attribute-kentän merkkien on oltava suurempi kuin :value.',
    ],
    'gte' => [
        'array' => ':attribute-kentällä on oltava vähintään :value kohdetta.',
        'file' => ':attribute-kentän tiedoston on oltava suurempi tai yhtä suuri kuin :value kilotavua.',
        'numeric' => ':attribute-kentän on oltava suurempi tai yhtä suuri kuin :value.',
        'string' => ':attribute-kentän merkkien on oltava suurempi tai yhtä suuri kuin :value.',
    ],
    'image' => ':attribute-kentän on oltava kuva.',
    'in' => 'Valittu :attribute ei ole kelvollinen.',
    'in_array' => ':attribute-kentän on oltava olemassa :other.',
    'integer' => ':attribute-kentän on oltava kokonaisluku.',
    'ip' => ':attribute-kentän on oltava kelvollinen IP-osoite.',
    'ipv4' => ':attribute-kentän on oltava kelvollinen IPv4-osoite.',
    'ipv6' => ':attribute-kentän on oltava kelvollinen IPv6-osoite.',
    'json' => ':attribute-kentän on oltava kelvollinen JSON-merkkijono.',
    'max' => [
        'array' => ':attribute-kentällä ei saa olla enempää kuin :max kohdetta.',
        'file' => ':attribute-kentän tiedosto ei saa olla suurempi kuin :max kilotavua.',
        'numeric' => ':attribute-kentän on oltava enintään :max.',
        'string' => ':attribute-kentän merkkien on oltava enintään :max.',
    ],
    'mimes' => ':attribute-kentän tiedoston on oltava tyyppiä: :values.',
    'min' => [
        'array' => ':attribute-kentällä on oltava vähintään :min kohdetta.',
        'file' => ':attribute-kentän tiedoston on oltava vähintään :min kilotavua.',
        'numeric' => ':attribute-kentän on oltava vähintään :min.',
        'string' => ':attribute-kentän merkkien on oltava vähintään :min.',
    ],
    'not_in' => 'Valittu :attribute ei ole kelvollinen.',
    'numeric' => ':attribute-kentän on oltava numero.',
    'present' => ':attribute-kentän on oltava olemassa.',
    'regex' => ':attribute-kentän muoto on virheellinen.',
    'required' => ':attribute-kenttä on pakollinen.',
    'required_if' => ':attribute-kenttä on pakollinen, kun :other on :value.',
    'same' => ':attribute-kentän on täsmättävä :other.',
    'size' => [
        'array' => ':attribute-kentällä on oltava :size kohdetta.',
        'file' => ':attribute-kentän tiedoston on oltava :size kilotavua.',
        'numeric' => ':attribute-kentän on oltava :size.',
        'string' => ':attribute-kentän merkkien on oltava :size.',
    ],
    'string' => ':attribute-kentän on oltava merkkijono.',
    'timezone' => ':attribute-kentän on oltava kelvollinen aikavyöhyke.',
    'unique' => ':attribute on jo käytössä.',
    'uploaded' => ':attribute:n lataaminen epäonnistui.',
    'url' => ':attribute-kentän on oltava kelvollinen URL.',
    'uuid' => ':attribute-kentän on oltava kelvollinen UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [],

];
