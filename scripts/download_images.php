<?php
// scripts/download_images.php

class ImageDownloader {
    private $baseDir;
    private $imageUrls = [
        'events' => [
            'music' => [
                'https://images.unsplash.com/photo-1470225620780-dba8ba36b745' => 'concert-1.jpg',
                'https://images.unsplash.com/photo-1501386761578-eac5c94b800a' => 'concert-2.jpg',
                'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3' => 'festival-1.jpg',
                'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7' => 'festival-2.jpg'
            ],
            'sports' => [
                'https://images.unsplash.com/photo-1461896836934-ffe607ba8211' => 'stadium-1.jpg',
                'https://images.unsplash.com/photo-1519389950473-47ba0277781c' => 'stadium-2.jpg',
                'https://images.unsplash.com/photo-1471295253337-3ceaaedca402' => 'match-1.jpg',
                'https://images.unsplash.com/photo-1577471488278-16eec37ffcc2' => 'match-2.jpg'
            ],
            'technology' => [
                'https://images.unsplash.com/photo-1505373877841-8d25f7d46678' => 'conference-1.jpg',
                'https://images.unsplash.com/photo-1540575467063-178a50c2df87' => 'conference-2.jpg',
                'https://images.unsplash.com/photo-1591115765373-5207764f72e4' => 'workshop-1.jpg',
                'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab' => 'workshop-2.jpg'
            ],
            'art' => [
                'https://images.unsplash.com/photo-1460661419201-fd4cecdf8a8b' => 'gallery-1.jpg',
                'https://images.unsplash.com/photo-1513364776144-60967b0f800f' => 'gallery-2.jpg',
                'https://images.unsplash.com/photo-1499781350541-7783f6c6a0c8' => 'exhibition-1.jpg',
                'https://images.unsplash.com/photo-1544967082-d9d25d867d66' => 'exhibition-2.jpg'
            ]
        ],
        'categories' => [
            'music.jpg' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4',
            'sports.jpg' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211',
            'technology.jpg' => 'https://images.unsplash.com/photo-1519389950473-47ba0277781c',
            'art.jpg' => 'https://images.unsplash.com/photo-1460661419201-fd4cecdf8a8b'
        ]
    ];

    public function __construct() {
        $this->baseDir = dirname(__DIR__) . '/assets/images/defaults/';
    }

    public function downloadImages() {
        // Create base directory if it doesn't exist
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }

        // Download event images
        foreach ($this->imageUrls['events'] as $category => $images) {
            $categoryDir = $this->baseDir . 'events/' . $category . '/';
            if (!is_dir($categoryDir)) {
                mkdir($categoryDir, 0777, true);
            }

            foreach ($images as $url => $filename) {
                $this->downloadImage($url, $categoryDir . $filename);
                echo "Downloaded: $filename\n";
            }
        }

        // Download category images
        $categoryDir = $this->baseDir . 'categories/';
        if (!is_dir($categoryDir)) {
            mkdir($categoryDir, 0777, true);
        }

        foreach ($this->imageUrls['categories'] as $filename => $url) {
            $this->downloadImage($url, $categoryDir . $filename);
            echo "Downloaded: $filename\n";
        }
    }

    private function downloadImage($url, $path) {
        // Add Unsplash parameters for proper sizing
        $url .= '?auto=format&fit=crop&w=800&q=80';
        
        // Download image using cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $data = curl_exec($ch);
        curl_close($ch);

        if ($data) {
            file_put_contents($path, $data);
        }
    }
}

// Run the downloader
$downloader = new ImageDownloader();
$downloader->downloadImages();

echo "Image download complete!\n";
?>