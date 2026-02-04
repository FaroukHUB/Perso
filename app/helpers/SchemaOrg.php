<?php
/**
 * PERSONNALY - SchemaOrg Helper
 * Génération de données structurées Schema.org
 */

class SchemaOrg
{
    private static $siteName = 'PERSONNALY';
    private static $siteUrl = 'https://personnaly.fr';
    private static $siteLogo = '/public/assets/images/logo.png';

    /**
     * Configure le helper
     */
    public static function configure(string $siteName, string $siteUrl, ?string $logo = null): void
    {
        self::$siteName = $siteName;
        self::$siteUrl = rtrim($siteUrl, '/');
        if ($logo) {
            self::$siteLogo = $logo;
        }
    }

    /**
     * Génère le JSON-LD pour une organisation
     */
    public static function organization(array $data = []): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $data['name'] ?? self::$siteName,
            'url' => $data['url'] ?? self::$siteUrl,
            'logo' => self::$siteUrl . ($data['logo'] ?? self::$siteLogo),
        ];

        if (!empty($data['description'])) {
            $schema['description'] = $data['description'];
        }

        if (!empty($data['email'])) {
            $schema['email'] = $data['email'];
        }

        if (!empty($data['phone'])) {
            $schema['telephone'] = $data['phone'];
        }

        if (!empty($data['address'])) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $data['address']['street'] ?? '',
                'addressLocality' => $data['address']['city'] ?? '',
                'postalCode' => $data['address']['zip'] ?? '',
                'addressCountry' => $data['address']['country'] ?? 'FR'
            ];
        }

        if (!empty($data['social'])) {
            $schema['sameAs'] = array_values($data['social']);
        }

        return self::toJsonLd($schema);
    }

    /**
     * Génère le JSON-LD pour une page web
     */
    public static function webPage(array $data): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $data['title'],
            'url' => $data['url'] ?? self::$siteUrl . '/' . ($data['slug'] ?? ''),
        ];

        if (!empty($data['description'])) {
            $schema['description'] = $data['description'];
        }

        if (!empty($data['image'])) {
            $schema['image'] = self::$siteUrl . $data['image'];
        }

        if (!empty($data['datePublished'])) {
            $schema['datePublished'] = $data['datePublished'];
        }

        if (!empty($data['dateModified'])) {
            $schema['dateModified'] = $data['dateModified'];
        }

        return self::toJsonLd($schema);
    }

    /**
     * Génère le JSON-LD pour un produit
     */
    public static function product(array $product): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product['name'],
            'description' => $product['description'] ?? '',
            'url' => self::$siteUrl . '/produit/' . ($product['slug'] ?? $product['id']),
            'sku' => $product['sku'] ?? 'PROD-' . $product['id'],
            'brand' => [
                '@type' => 'Brand',
                'name' => self::$siteName
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => number_format($product['price'] ?? $product['base_price'], 2, '.', ''),
                'priceCurrency' => 'EUR',
                'availability' => $product['in_stock'] ?? true
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => self::$siteUrl . '/produit/' . ($product['slug'] ?? $product['id']),
                'seller' => [
                    '@type' => 'Organization',
                    'name' => self::$siteName
                ]
            ]
        ];

        if (!empty($product['image_front_url'])) {
            $schema['image'] = self::$siteUrl . $product['image_front_url'];
        }

        if (!empty($product['rating'])) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $product['rating'],
                'reviewCount' => $product['review_count'] ?? 1
            ];
        }

        return self::toJsonLd($schema);
    }

    /**
     * Génère le JSON-LD pour une FAQ
     */
    public static function faqPage(array $faqs): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => []
        ];

        foreach ($faqs as $faq) {
            $schema['mainEntity'][] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer']
                ]
            ];
        }

        return self::toJsonLd($schema);
    }

    /**
     * Génère le JSON-LD pour un article de blog
     */
    public static function blogPost(array $post): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post['title'],
            'url' => self::$siteUrl . '/article/' . ($post['slug'] ?? $post['id']),
            'datePublished' => $post['published_at'] ?? $post['created_at'],
            'dateModified' => $post['updated_at'] ?? $post['created_at'],
            'author' => [
                '@type' => 'Organization',
                'name' => self::$siteName,
                'url' => self::$siteUrl
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => self::$siteName,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => self::$siteUrl . self::$siteLogo
                ]
            ]
        ];

        if (!empty($post['excerpt']) || !empty($post['description'])) {
            $schema['description'] = $post['excerpt'] ?? $post['description'];
        }

        if (!empty($post['cover_image_url'])) {
            $schema['image'] = self::$siteUrl . $post['cover_image_url'];
        }

        return self::toJsonLd($schema);
    }

    /**
     * Génère le JSON-LD pour un fil d'Ariane
     */
    public static function breadcrumb(array $items): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => []
        ];

        foreach ($items as $position => $item) {
            $schema['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $item['name'],
                'item' => self::$siteUrl . ($item['url'] ?? '')
            ];
        }

        return self::toJsonLd($schema);
    }

    /**
     * Génère le JSON-LD pour des témoignages
     */
    public static function reviews(array $reviews, string $itemName, string $itemUrl): string
    {
        $totalRating = 0;
        $reviewSchemas = [];

        foreach ($reviews as $review) {
            $rating = $review['rating'] ?? 5;
            $totalRating += $rating;

            $reviewSchemas[] = [
                '@type' => 'Review',
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => $rating,
                    'bestRating' => '5'
                ],
                'author' => [
                    '@type' => 'Person',
                    'name' => $review['author_name'] ?? 'Client'
                ],
                'reviewBody' => $review['content'] ?? $review['text'] ?? ''
            ];
        }

        $avgRating = count($reviews) > 0 ? round($totalRating / count($reviews), 1) : 5;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $itemName,
            'url' => $itemUrl,
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => $avgRating,
                'reviewCount' => count($reviews),
                'bestRating' => '5',
                'worstRating' => '1'
            ],
            'review' => $reviewSchemas
        ];

        return self::toJsonLd($schema);
    }

    /**
     * Génère le JSON-LD pour un commerce local
     */
    public static function localBusiness(array $data): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $data['name'] ?? self::$siteName,
            'url' => $data['url'] ?? self::$siteUrl,
            '@id' => self::$siteUrl
        ];

        if (!empty($data['image'])) {
            $schema['image'] = self::$siteUrl . $data['image'];
        }

        if (!empty($data['phone'])) {
            $schema['telephone'] = $data['phone'];
        }

        if (!empty($data['email'])) {
            $schema['email'] = $data['email'];
        }

        if (!empty($data['priceRange'])) {
            $schema['priceRange'] = $data['priceRange'];
        }

        if (!empty($data['address'])) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $data['address']['street'] ?? '',
                'addressLocality' => $data['address']['city'] ?? '',
                'postalCode' => $data['address']['zip'] ?? '',
                'addressCountry' => $data['address']['country'] ?? 'FR'
            ];
        }

        if (!empty($data['geo'])) {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $data['geo']['lat'],
                'longitude' => $data['geo']['lng']
            ];
        }

        if (!empty($data['openingHours'])) {
            $schema['openingHoursSpecification'] = [];
            foreach ($data['openingHours'] as $day => $hours) {
                $schema['openingHoursSpecification'][] = [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => $day,
                    'opens' => $hours['opens'] ?? '09:00',
                    'closes' => $hours['closes'] ?? '18:00'
                ];
            }
        }

        return self::toJsonLd($schema);
    }

    /**
     * Convertit un tableau en balise script JSON-LD
     */
    private static function toJsonLd(array $data): string
    {
        return '<script type="application/ld+json">' . "\n"
            . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            . "\n</script>";
    }
}
