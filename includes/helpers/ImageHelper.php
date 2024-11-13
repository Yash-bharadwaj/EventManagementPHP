<?php
// includes/helpers/ImageHelper.php

class ImageHelper {
    private static $defaultImages = [
        'events' => [
            'music' => [
                'concert-1.jpg',
                'concert-2.jpg',
                'festival-1.jpg',
                'festival-2.jpg'
            ],
            'sports' => [
                'stadium-1.jpg',
                'stadium-2.jpg',
                'match-1.jpg',
                'match-2.jpg'
            ],
            'technology' => [
                'conference-1.jpg',
                'conference-2.jpg',
                'workshop-1.jpg',
                'workshop-2.jpg'
            ],
            'art' => [
                'gallery-1.jpg',
                'gallery-2.jpg',
                'exhibition-1.jpg',
                'exhibition-2.jpg'
            ]
        ]
    ];

    public static function getDefaultEventImage($category) {
        $category = strtolower($category);
        $images = self::$defaultImages['events'][$category] ?? self::$defaultImages['events']['music'];
        $randomIndex = array_rand($images);
        return '/assets/images/defaults/events/' . $category . '/' . $images[$randomIndex];
    }

    public static function getCategoryImage($category) {
        return '/assets/images/defaults/categories/' . strtolower($category) . '.jpg';
    }

    public static function getEventImage($event) {
        if (!empty($event['image_url'])) {
            return $event['image_url'];
        }
        return self::getDefaultEventImage($event['category_name'] ?? 'music');
    }
}
?>