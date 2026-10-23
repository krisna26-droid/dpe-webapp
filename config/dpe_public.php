<?php

return [
    // Preview only: never inserts programs, branches, or contact data.
    'sample_content' => env('DPE_PUBLIC_SAMPLE_CONTENT', env('APP_ENV') === 'local'),

    'sample_programs' => [
        [
            'id' => 'regular', 'name' => 'Regular', 'type_label' => 'Kelas reguler',
            'description' => 'Belajar bersama dalam kelas yang membantu kamu membangun dasar bahasa Inggris secara bertahap.',
            'audience' => 'Pelajar yang ingin belajar bersama',
            'features' => ['Latihan speaking dan listening', 'Pengembangan reading dan writing', 'Belajar bersama teman sekelas'],
        ],
        [
            'id' => 'general-private', 'name' => 'General Private', 'type_label' => 'Kelas privat',
            'description' => 'Pilihan belajar privat dengan fokus pada kebutuhan dan tujuan bahasa Inggris kamu.',
            'audience' => 'Pelajar dengan kebutuhan belajar personal',
            'features' => ['Fokus pada kebutuhan belajar', 'Latihan komunikasi sehari-hari', 'Pendampingan dalam kelas privat'],
        ],
        [
            'id' => 'esp-private', 'name' => 'ESP Private', 'type_label' => 'Bahasa Inggris khusus',
            'description' => 'English for Specific Purposes: pilihan belajar privat untuk kebutuhan bahasa Inggris yang lebih spesifik.',
            'audience' => 'Pelajar dengan tujuan bahasa Inggris khusus',
            'features' => ['Kosakata sesuai kebutuhan', 'Latihan dalam konteks yang relevan', 'Materi berorientasi tujuan belajar'],
        ],
    ],

    'sample_branches' => ['GatSu', 'Batubulan', 'Renon', 'Dalung', 'Sukawati'],
];
