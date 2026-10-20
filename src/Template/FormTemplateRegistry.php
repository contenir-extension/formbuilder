<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Template;

use function array_key_exists;
use function array_values;

/**
 * Curated catalogue of built-in form templates surfaced on the Forms home's
 * Add form menu.
 *
 * Each entry is a {@see FormTemplate}; adding a new template is a
 * one-method addition here. Custom templates can be merged in by passing a
 * list to the constructor.
 *
 * @mago-expect lint:too-many-methods One private method per built-in template keeps each seed readable.
 */
final class FormTemplateRegistry
{
    /** @var array<string, FormTemplate> */
    private array $templates = [];

    /** @param list<FormTemplate> $extra */
    public function __construct(array $extra = [])
    {
        foreach ($this->builtIn() as $template) {
            $this->templates[$template->slug] = $template;
        }
        foreach ($extra as $template) {
            $this->templates[$template->slug] = $template;
        }
    }

    /** @return list<FormTemplate> */
    public function all(): array
    {
        return array_values($this->templates);
    }

    public function get(string $slug): ?FormTemplate
    {
        return $this->templates[$slug] ?? null;
    }

    public function has(string $slug): bool
    {
        return array_key_exists($slug, $this->templates);
    }

    private function bookingRequest(): FormTemplate
    {
        return new FormTemplate(
            slug: 'booking-request',
            name: 'Booking request',
            description: 'Date-driven enquiry — guest count, dates, special requests.',
            icon: 'bed',
            titleSuggestion: 'Booking enquiry',
            slugSuggestion: 'booking',
            sections: [
                [
                    'key'    => 'main',
                    'groups' => [
                        [
                            'legend' => 'Contact',
                            'rows'   => [
                                [
                                    'fields' => [
                                        [
                                            'type'     => 'text',
                                            'name'     => 'name',
                                            'label'    => 'Full name',
                                            'required' => true,
                                        ],
                                        ['type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true],
                                    ],
                                ],
                                [
                                    'fields' => [
                                        ['type' => 'tel', 'name' => 'phone', 'label' => 'Phone'],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'legend' => 'Stay',
                            'rows'   => [
                                [
                                    'fields' => [
                                        [
                                            'type'     => 'date',
                                            'name'     => 'arrival',
                                            'label'    => 'Arrival',
                                            'required' => true,
                                        ],
                                        [
                                            'type'     => 'date',
                                            'name'     => 'departure',
                                            'label'    => 'Departure',
                                            'required' => true,
                                        ],
                                    ],
                                ],
                                [
                                    'fields' => [
                                        [
                                            'type'          => 'number',
                                            'name'          => 'guests',
                                            'label'         => 'Number of guests',
                                            'default_value' => '2',
                                            'required'      => true,
                                        ],
                                    ],
                                ],
                                [
                                    'fields' => [
                                        ['type' => 'textarea', 'name' => 'notes', 'label' => 'Special requests'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );
    }

    /** @return list<FormTemplate> */
    private function builtIn(): array
    {
        return [
            $this->contact(),
            $this->newsletter(),
            $this->productEnquiry(),
            $this->eventRegistration(),
            $this->bookingRequest(),
            $this->feedback(),
        ];
    }

    private function contact(): FormTemplate
    {
        return new FormTemplate(
            slug: 'contact',
            name: 'Contact form',
            description: 'Standard name / email / message — the default for "get in touch" pages.',
            icon: 'mail',
            titleSuggestion: 'Contact us',
            slugSuggestion: 'contact',
            sections: [
                [
                    'key'    => 'main',
                    'groups' => [
                        [
                            'rows' => [
                                [
                                    'fields' => [
                                        [
                                            'type'     => 'text',
                                            'name'     => 'name',
                                            'label'    => 'Your name',
                                            'required' => true,
                                        ],
                                    ],
                                ],
                                [
                                    'fields' => [
                                        ['type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true],
                                    ],
                                ],
                                [
                                    'fields' => [
                                        [
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
        );
    }

    private function eventRegistration(): FormTemplate
    {
        return new FormTemplate(
            slug: 'event-registration',
            name: 'Event registration',
            description: 'Attendee details + party size + dietary notes. Adapts to any event.',
            icon: 'calendar-event',
            titleSuggestion: 'Event registration',
            slugSuggestion: 'event-registration',
            sections: [
                [
                    'key'    => 'main',
                    'groups' => [
                        [
                            'legend' => 'Attendee',
                            'rows'   => [
                                [
                                    'fields' => [
                                        [
                                            'type'     => 'text',
                                            'name'     => 'name',
                                            'label'    => 'Full name',
                                            'required' => true,
                                        ],
                                        ['type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true],
                                    ],
                                ],
                                [
                                    'fields' => [
                                        ['type' => 'tel', 'name' => 'phone', 'label' => 'Phone'],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'legend' => 'Attending',
                            'rows'   => [
                                [
                                    'fields' => [
                                        [
                                            'type'          => 'number',
                                            'name'          => 'guests',
                                            'label'         => 'Number of guests',
                                            'default_value' => '1',
                                            'required'      => true,
                                        ],
                                    ],
                                ],
                                [
                                    'fields' => [
                                        ['type' => 'textarea', 'name' => 'dietary', 'label' => 'Dietary requirements'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );
    }

    private function feedback(): FormTemplate
    {
        return new FormTemplate(
            slug: 'feedback',
            name: 'Feedback survey',
            description: 'Short rating + open comment. Good for "how did we do" follow-ups.',
            icon: 'thumb-up',
            titleSuggestion: 'Tell us how we did',
            slugSuggestion: 'feedback',
            sections: [
                [
                    'key'    => 'main',
                    'groups' => [
                        [
                            'rows' => [
                                [
                                    'fields' => [
                                        [
                                            'type'     => 'radio',
                                            'name'     => 'rating',
                                            'label'    => 'Overall rating',
                                            'options'  => [
                                                'choices' => [
                                                    ['value' => '5', 'label' => 'Excellent'],
                                                    ['value' => '4', 'label' => 'Good'],
                                                    ['value' => '3', 'label' => 'OK'],
                                                    ['value' => '2', 'label' => 'Poor'],
                                                    ['value' => '1', 'label' => 'Terrible'],
                                                ],
                                            ],
                                            'required' => true,
                                        ],
                                    ],
                                ],
                                [
                                    'fields' => [
                                        ['type' => 'textarea', 'name' => 'comment', 'label' => 'Tell us more'],
                                    ],
                                ],
                                [
                                    'fields' => [
                                        ['type' => 'email', 'name' => 'email', 'label' => 'Email (optional)'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );
    }

    private function newsletter(): FormTemplate
    {
        return new FormTemplate(
            slug: 'newsletter',
            name: 'Newsletter signup',
            description: 'Tight email-only signup, optional first name. Drop on a footer or inline CTA.',
            icon: 'mail-fast',
            titleSuggestion: 'Newsletter signup',
            slugSuggestion: 'newsletter',
            sections: [
                [
                    'key'    => 'main',
                    'groups' => [
                        [
                            'rows' => [
                                [
                                    'fields' => [
                                        ['type' => 'text', 'name' => 'first_name', 'label' => 'First name'],
                                    ],
                                ],
                                [
                                    'fields' => [
                                        ['type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );
    }

    private function productEnquiry(): FormTemplate
    {
        return new FormTemplate(
            slug: 'product-enquiry',
            name: 'Product enquiry',
            description: 'Lead form with contact details + product interest + free-text question.',
            icon: 'package',
            titleSuggestion: 'Product enquiry',
            slugSuggestion: 'product-enquiry',
            sections: [
                [
                    'key'    => 'main',
                    'groups' => [
                        [
                            'legend' => 'Your details',
                            'rows'   => [
                                [
                                    'fields' => [
                                        [
                                            'type'     => 'text',
                                            'name'     => 'name',
                                            'label'    => 'Full name',
                                            'required' => true,
                                        ],
                                        ['type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true],
                                    ],
                                ],
                                [
                                    'fields' => [
                                        ['type' => 'tel', 'name' => 'phone', 'label' => 'Phone'],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'legend' => 'Your enquiry',
                            'rows'   => [
                                [
                                    'fields' => [
                                        ['type' => 'text', 'name' => 'product', 'label' => 'Product of interest'],
                                    ],
                                ],
                                [
                                    'fields' => [
                                        [
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
        );
    }
}
