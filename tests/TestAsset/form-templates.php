<?php

declare(strict_types=1);

/**
 * The built-in templates as each seeds a form: a snapshot the registry test
 * compares against, so a change to a seed is a deliberate one.
 */
return [
    'contact'            => [
        'slug'            => 'contact',
        'name'            => 'Contact form',
        'description'     => 'Standard name / email / message — the default for "get in touch" pages.',
        'icon'            => 'mail',
        'titleSuggestion' => 'Contact us',
        'slugSuggestion'  => 'contact',
        'sections'        => [
            0 => [
                'key'    => 'main',
                'groups' => [
                    0 => [
                        'rows' => [
                            0 => [
                                'fields' => [
                                    0 => [
                                        'type'     => 'text',
                                        'name'     => 'name',
                                        'label'    => 'Your name',
                                        'required' => true,
                                    ],
                                ],
                            ],
                            1 => [
                                'fields' => [
                                    0 => [
                                        'type'     => 'email',
                                        'name'     => 'email',
                                        'label'    => 'Email',
                                        'required' => true,
                                    ],
                                ],
                            ],
                            2 => [
                                'fields' => [
                                    0 => [
                                        'type'     => 'textarea',
                                        'name'     => 'message',
                                        'label'    => 'Message',
                                        'required' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'newsletter'         => [
        'slug'            => 'newsletter',
        'name'            => 'Newsletter signup',
        'description'     => 'Tight email-only signup, optional first name. Drop on a footer or inline CTA.',
        'icon'            => 'mail-fast',
        'titleSuggestion' => 'Newsletter signup',
        'slugSuggestion'  => 'newsletter',
        'sections'        => [
            0 => [
                'key'    => 'main',
                'groups' => [
                    0 => [
                        'rows' => [
                            0 => [
                                'fields' => [
                                    0 => [
                                        'type'  => 'text',
                                        'name'  => 'first_name',
                                        'label' => 'First name',
                                    ],
                                ],
                            ],
                            1 => [
                                'fields' => [
                                    0 => [
                                        'type'     => 'email',
                                        'name'     => 'email',
                                        'label'    => 'Email',
                                        'required' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'product-enquiry'    => [
        'slug'            => 'product-enquiry',
        'name'            => 'Product enquiry',
        'description'     => 'Lead form with contact details + product interest + free-text question.',
        'icon'            => 'package',
        'titleSuggestion' => 'Product enquiry',
        'slugSuggestion'  => 'product-enquiry',
        'sections'        => [
            0 => [
                'key'    => 'main',
                'groups' => [
                    0 => [
                        'legend' => 'Your details',
                        'rows'   => [
                            0 => [
                                'fields' => [
                                    0 => [
                                        'type'     => 'text',
                                        'name'     => 'name',
                                        'label'    => 'Full name',
                                        'required' => true,
                                    ],
                                    1 => [
                                        'type'     => 'email',
                                        'name'     => 'email',
                                        'label'    => 'Email',
                                        'required' => true,
                                    ],
                                ],
                            ],
                            1 => [
                                'fields' => [
                                    0 => [
                                        'type'  => 'tel',
                                        'name'  => 'phone',
                                        'label' => 'Phone',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    1 => [
                        'legend' => 'Your enquiry',
                        'rows'   => [
                            0 => [
                                'fields' => [
                                    0 => [
                                        'type'  => 'text',
                                        'name'  => 'product',
                                        'label' => 'Product of interest',
                                    ],
                                ],
                            ],
                            1 => [
                                'fields' => [
                                    0 => [
                                        'type'     => 'textarea',
                                        'name'     => 'question',
                                        'label'    => 'Question or comment',
                                        'required' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'event-registration' => [
        'slug'            => 'event-registration',
        'name'            => 'Event registration',
        'description'     => 'Attendee details + party size + dietary notes. Adapts to any event.',
        'icon'            => 'calendar-event',
        'titleSuggestion' => 'Event registration',
        'slugSuggestion'  => 'event-registration',
        'sections'        => [
            0 => [
                'key'    => 'main',
                'groups' => [
                    0 => [
                        'legend' => 'Attendee',
                        'rows'   => [
                            0 => [
                                'fields' => [
                                    0 => [
                                        'type'     => 'text',
                                        'name'     => 'name',
                                        'label'    => 'Full name',
                                        'required' => true,
                                    ],
                                    1 => [
                                        'type'     => 'email',
                                        'name'     => 'email',
                                        'label'    => 'Email',
                                        'required' => true,
                                    ],
                                ],
                            ],
                            1 => [
                                'fields' => [
                                    0 => [
                                        'type'  => 'tel',
                                        'name'  => 'phone',
                                        'label' => 'Phone',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    1 => [
                        'legend' => 'Attending',
                        'rows'   => [
                            0 => [
                                'fields' => [
                                    0 => [
                                        'type'          => 'number',
                                        'name'          => 'guests',
                                        'label'         => 'Number of guests',
                                        'default_value' => '1',
                                        'required'      => true,
                                    ],
                                ],
                            ],
                            1 => [
                                'fields' => [
                                    0 => [
                                        'type'  => 'textarea',
                                        'name'  => 'dietary',
                                        'label' => 'Dietary requirements',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'booking-request'    => [
        'slug'            => 'booking-request',
        'name'            => 'Booking request',
        'description'     => 'Date-driven enquiry — guest count, dates, special requests.',
        'icon'            => 'bed',
        'titleSuggestion' => 'Booking enquiry',
        'slugSuggestion'  => 'booking',
        'sections'        => [
            0 => [
                'key'    => 'main',
                'groups' => [
                    0 => [
                        'legend' => 'Contact',
                        'rows'   => [
                            0 => [
                                'fields' => [
                                    0 => [
                                        'type'     => 'text',
                                        'name'     => 'name',
                                        'label'    => 'Full name',
                                        'required' => true,
                                    ],
                                    1 => [
                                        'type'     => 'email',
                                        'name'     => 'email',
                                        'label'    => 'Email',
                                        'required' => true,
                                    ],
                                ],
                            ],
                            1 => [
                                'fields' => [
                                    0 => [
                                        'type'  => 'tel',
                                        'name'  => 'phone',
                                        'label' => 'Phone',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    1 => [
                        'legend' => 'Stay',
                        'rows'   => [
                            0 => [
                                'fields' => [
                                    0 => [
                                        'type'     => 'date',
                                        'name'     => 'arrival',
                                        'label'    => 'Arrival',
                                        'required' => true,
                                    ],
                                    1 => [
                                        'type'     => 'date',
                                        'name'     => 'departure',
                                        'label'    => 'Departure',
                                        'required' => true,
                                    ],
                                ],
                            ],
                            1 => [
                                'fields' => [
                                    0 => [
                                        'type'          => 'number',
                                        'name'          => 'guests',
                                        'label'         => 'Number of guests',
                                        'default_value' => '2',
                                        'required'      => true,
                                    ],
                                ],
                            ],
                            2 => [
                                'fields' => [
                                    0 => [
                                        'type'  => 'textarea',
                                        'name'  => 'notes',
                                        'label' => 'Special requests',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'feedback'           => [
        'slug'            => 'feedback',
        'name'            => 'Feedback survey',
        'description'     => 'Short rating + open comment. Good for "how did we do" follow-ups.',
        'icon'            => 'thumb-up',
        'titleSuggestion' => 'Tell us how we did',
        'slugSuggestion'  => 'feedback',
        'sections'        => [
            0 => [
                'key'    => 'main',
                'groups' => [
                    0 => [
                        'rows' => [
                            0 => [
                                'fields' => [
                                    0 => [
                                        'type'     => 'radio',
                                        'name'     => 'rating',
                                        'label'    => 'Overall rating',
                                        'options'  => [
                                            'choices' => [
                                                0 => [
                                                    'value' => '5',
                                                    'label' => 'Excellent',
                                                ],
                                                1 => [
                                                    'value' => '4',
                                                    'label' => 'Good',
                                                ],
                                                2 => [
                                                    'value' => '3',
                                                    'label' => 'OK',
                                                ],
                                                3 => [
                                                    'value' => '2',
                                                    'label' => 'Poor',
                                                ],
                                                4 => [
                                                    'value' => '1',
                                                    'label' => 'Terrible',
                                                ],
                                            ],
                                        ],
                                        'required' => true,
                                    ],
                                ],
                            ],
                            1 => [
                                'fields' => [
                                    0 => [
                                        'type'  => 'textarea',
                                        'name'  => 'comment',
                                        'label' => 'Tell us more',
                                    ],
                                ],
                            ],
                            2 => [
                                'fields' => [
                                    0 => [
                                        'type'  => 'email',
                                        'name'  => 'email',
                                        'label' => 'Email (optional)',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
