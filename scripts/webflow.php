<?php

define('WEBFLOW_CLIENT_ID', '08b2356257936cf08ef19de0a702f8ecbbb2aa38f6ad52cd537a798e49f0a90b');
define('WEBFLOW_CLIENT_SECRET', '4514bc985315b06cf9904e807f2d691032282b947e76228e7d0c3a2614ad90e8');
define('WEBFLOW_CODE', '9930bfd1003bd8f5654733363030490f20b41d494cbe2e45ac28659a81fcab18');

define('WEBFLOW_ACCESS_TOKEN', '4d58817851071e6d95065cf7941ba53095127995a662bfe657e178f9e07abfb8');
define('WEBFLOW_SITEID', '5f16134a4f95ff592575ca08');

include('db.php');

function slugify($text)
{
    // replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);

    // transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

    // remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);

    // trim
    $text = trim($text, '-');

    // remove duplicate -
    $text = preg_replace('~-+~', '-', $text);

    // lowercase
    $text = strtolower($text);

    if (empty($text)) {
        return 'n-a';
    }

    return $text;
}


function webflow_get($url)
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_HTTPHEADER => array(
            "accept-version: 1.0.0",
            "Authorization: Bearer " . WEBFLOW_ACCESS_TOKEN
        ),
        CURLOPT_RETURNTRANSFER => true,
    ));

    $response = curl_exec($curl);

    $data = json_decode($response, true);

    curl_close($curl);

    return $data;
}

function authorize_token()
{
    $url = "https://api.webflow.com/oauth/access_token";

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $url . "?client_id=" . WEBFLOW_CLIENT_ID . "&client_secret=" .  WEBFLOW_CLIENT_SECRET . "&code=" . WEBFLOW_CODE . "&grant_type=authorization_code",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => "POST",
    ));

    $response = curl_exec($curl);

    $data = json_decode($response, true);

    curl_close($curl);

    if (isset($data['access_token'])) {
        return $data['access_token'];
    } else {
        return FALSE;
    }
}


function get_info()
{
    $info = webflow_get("https://api.webflow.com/info");
    return $info;
}

function get_sites()
{
    $sites = webflow_get("https://api.webflow.com/sites");

    foreach ($sites as $site) {
        insert_table('sites', [
            'id' => $site['_id'],
            'name' => $site['name'],
            'shortName' => $site['shortName'],
        ]);
    }

    return $sites;
}

function get_collections($site_id)
{
    $collections = webflow_get("https://api.webflow.com/sites/" . $site_id . "/collections");

    foreach ($collections as $collection) {
        insert_table('collections', [
            'id' => $collection['_id'],
            'name' => $collection['name'],
            'slug' => $collection['slug'],
            'site_id' => $site_id
        ]);
    }

    return $collections;
}

function get_items($collection, $table)
{
    error_log('Get ' . $table . ' from Webflow.');

    delete_table($table);

    $data = webflow_get("https://api.webflow.com/collections/$collection/items");
    if(!isset($data['items'])) {
        return FALSE;
    }
    
    $items = $data['items'];

    foreach ($items as $item) {
        insert_table($table, [
            'id' => $item['_id'],
            'name' => $item['name'],
            'slug' => $item['slug'],
            'cid' => $collection
        ]);
    }

    error_log('Total ' . $table . ': ' . count($items));

    return count($items);
}


function get_properties($collection)
{
    error_log('Get properties from Webflow.');

    delete_table('properties');

    $data = webflow_get("https://api.webflow.com/collections/$collection/items");
    if(!isset($data['items'])) {
        return FALSE;
    }

    $items = $data['items'];

    foreach ($items as $item) {

        $image = $item['image-1']['fileId'];
        $featured_image = $item['featured-image-for-seo-normal-sized-photo']['fileId'];
        $gallery_images = $item['gallery-images'];
        $gallery2_images = $item['gallery-images-2'];

        $gallery = [];
        foreach ($gallery_images as $gallery_image) {
            $gallery[] = $gallery_image['fileId'];
        }

        $gallery2 = [];
        foreach ($gallery2_images as $gallery_image) {
            $gallery2[] = $gallery_image['fileId'];
        }

        insert_table('properties', [
            'id' => $item['_id'],
            'name' => $item['name'],
            'slug' => $item['slug'],
            'cid' => $collection,

            'number-of-rooms' => $item['number-of-rooms'],
            'number-of-baths' => $item['number-of-baths'],
            'square-feet' => $item['square-feet'],
            'agent-contact-info' => $item['agent-contact-info'],
            'mls-description' => $item['mls-description'],
            'mls-r-number' => $item['mls-r-number'],

            'image' => $image,
            'feature-image' => $featured_image,
            'gallery-images' => json_encode($gallery),
            'gallery-images-2' => json_encode($gallery2),

            'price' => $item['price-2'],
            'city' => $item['city-tag'],
            'property-type' => $item['type-of-property'],
            'price-range' => $item['price-range-category'],
            'status' => $item['status']
        ]);
    }

    error_log('Total properties: ' . count($items));

    return count($items);
}


function insert_city($collection, $city)
{
    $slug = slugify($city);
    $params = [
        'fields' => [
            "name" => $city,
            "slug" => $slug,
            "_archived" => false,
            "_draft" => false,
        ]
    ];

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.webflow.com/collections/$collection/items?live=true",
        CURLOPT_HTTPHEADER => array(
            "accept-version: 1.0.0",
            "Authorization: Bearer " . WEBFLOW_ACCESS_TOKEN,
            "Content-Type: application/json",
        ),
        CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_RETURNTRANSFER => true,
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    $data = json_decode($response, TRUE);
    if (!isset($data['_id'])) {
        return FALSE;
    }

    $id = $data['_id'];
    error_log('City Id: ' . $id);

    return $id;
}


function insert_property($collection, $property)
{
    $photos = $property['photos'];

    $photo = "";
    $galleryImgs = [];
    $galleryImgs2 = [];

    if (!empty($photos)) {
        $photo = $photos[0];
        $idx = 0;
        foreach ($photos as $_photo) {
            $idx++;

            if ($idx <= 24) {
                $galleryImgs[] = $_photo;
            } else {
                $galleryImgs2[] = $_photo;
            }
        }
    }

    $slug = slugify($property['streetAddress']);
    $params = [
        'fields' => [
            "_archived" => false,
            "_draft" => false,
            "name" => $property['streetAddress'],
            "slug" => $slug,
            "agent-contact-info" => $property['agent'],
            "mls-description" => $property['remarks'],
            "mls-r-number" => $property['listingId'],
            "price-2" => $property['price'],
            "number-of-baths" => $property['bathrooms'],
            "number-of-rooms" => $property['bedrooms'],
            "square-feet" => $property['sqft'],
            "city-tag" => $property['cityId'],
            "type-of-property" => $property['typeId'],
            "price-range-category" => $property['priceId'],
            "status" => $property['statusId'],
            "image-1" => $photo,
            "featured-image-for-seo-normal-sized-photo" => $photo,
            "gallery-images" => $galleryImgs,
            "gallery-images-2" => $galleryImgs2
        ]
    ];

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.webflow.com/collections/$collection/items?live=true",
        CURLOPT_HTTPHEADER => array(
            "accept-version: 1.0.0",
            "Authorization: Bearer " . WEBFLOW_ACCESS_TOKEN,
            "Content-Type: application/json",
        ),
        CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_RETURNTRANSFER => true,
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    $data = json_decode($response, TRUE);

    error_log($response);


    if (!isset($data['_id'])) {
        return FALSE;
    }

    $id = $data['_id'];
    error_log('Property Id: ' . $id);
    exit();

    return TRUE;
}


function update_property($collection, $property, $dbProperty)
{
    $propertyId = $dbProperty['id'];

    $photo = "";
    $galleryImgs = [];
    $galleryImgs2 = [];

    $photos = $property['photos'];
    if (!empty($photos)) {
        $photo = $photos[0];
    }

    $idx = 0;
    foreach ($photos as $_photo) {
        $idx++;

        if ($idx <= 24) {
            $galleryImgs[] = $_photo;
        } else {
            $galleryImgs2[] = $_photo;
        }
    }

    $slug = slugify($property['streetAddress']);
    $fields = [
        "_archived" => false,
        "_draft" => false,
        "name" => $property['streetAddress'],
        "slug" => $slug,
        "agent-contact-info" => $property['agent'],
        "mls-description" => $property['remarks'],
        "mls-r-number" => $property['listingId'],
        "price-2" => $property['price'],
        "number-of-baths" => $property['bathrooms'],
        "number-of-rooms" => $property['bedrooms'],
        "square-feet" => $property['sqft'],
        "city-tag" => $property['cityId'],
        "type-of-property" => $property['typeId'],
        "price-range-category" => $property['priceId'],
        "status" => $property['statusId'],
    ];

    if (empty($dbProperty['image'])) {
        $fields['image-1'] = $photo;
    }

    if (empty($dbProperty['feature-image'])) {
        $fields['featured-image-for-seo-normal-sized-photo'] = $photo;
    }

    $galleryImages = json_decode($dbProperty['gallery-images'], TRUE);
    if (empty($galleryImages)) 
    {
        $fields['gallery-images'] = $galleryImgs;
    }

    $galleryImages2 = json_decode($dbProperty['gallery-images-2'], TRUE);
    if (empty($galleryImages2)) 
    {
        $fields['gallery-images-2'] = $galleryImgs2;
    }

    $params = [
        'fields' => $fields
    ];

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.webflow.com/collections/$collection/items/$propertyId?live=true",
        CURLOPT_HTTPHEADER => array(
            "accept-version: 1.0.0",
            "Authorization: Bearer " . WEBFLOW_ACCESS_TOKEN,
            "Content-Type: application/json",
        ),
        CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_CUSTOMREQUEST => "PUT",
        CURLOPT_RETURNTRANSFER => true,
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    $data = json_decode($response, TRUE);
    if (!isset($data['_id'])) {
        return FALSE;
    }

    $id = $data['_id'];
    error_log('Property Id: ' . $id);

    return TRUE;
}

function delete_property($collection, $id)
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.webflow.com/collections/$collection/items/$id",
        CURLOPT_HTTPHEADER => array(
            "accept-version: 1.0.0",
            "Authorization: Bearer " . WEBFLOW_ACCESS_TOKEN
        ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "DELETE"
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    error_log($response);
}

function publish_site()
{
    $params = [
        'domains' => [
            "andrewsthilaire.com",
            "andrew-st-hilaires-realtor-website.webflow.io",
            "www.andrewsthilaire.com"
        ]
    ];

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.webflow.com/sites/" . WEBFLOW_SITEID . "/publish",
        CURLOPT_HTTPHEADER => array(
            "accept-version: 1.0.0",
            "Authorization: Bearer " . WEBFLOW_ACCESS_TOKEN,
            "Content-Type: application/json",
        ),
        CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_RETURNTRANSFER => true,
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    error_log($response);
}


function get_id($table, $name)
{
    $_collection  = select_row($table, [
        'name' => $name
    ]);

    if (empty($_collection)) {
        return FALSE;
    }

    return $_collection['id'];
}

function get_city($city)
{
    $cityId = get_id('cities', $city);
    return $cityId;
}

function get_trans_type($transactionType)
{
    $transId = get_id('listing_status', $transactionType);
    return $transId;
}

function get_prop_type($property)
{
    $propertyType = "{$property->PropertyType}";
    if ($propertyType == "Retail") {
        $typeId = "5f28c44bf4e9782db472febb";
    } else if ($propertyType == "Vacant Land") {
        $typeId = "5f28c3453278064238d9d767";
    } else if ($propertyType == "Industrial") {
        $typeId = "5f28cebea5b59b3763dbb180";
    } else if ($propertyType == "Multi-family") {
        $typeId = "5f476ad468d4931c226cda30";
    } else {
        $building = $property->Building;
        $buildingType = "{$building->Type}";

        if ($buildingType == "Duplex") {
            $typeId = "5f28c3ff245a28f7a42fd965";
        } else if ($buildingType == "Tri-Plex") {
            $typeId = "5f28c40421daad7d3a56a20d";
        } else if ($buildingType == "Apartment") {
            $typeId = "5f28c3f203d9202264238038";
        } else if ($buildingType == "Commercial Mix") {
            $typeId = "5f28c421fef94bf75c1a6b92";
        } else {

            $architecturalStyle = "{$building->ArchitecturalStyle}";

            if (
                $architecturalStyle == "Bungalow"
                || $architecturalStyle == "Raised bungalow"
            ) {
                $typeId = "5f28c3334e58bf6e4e9d43ba";
            } else if ($architecturalStyle == "Condo") {
                $typeId = "5f28c33a28f2fd17429e5f8d";
            } else if ($architecturalStyle == "Bi-level") {
                $typeId = "5f28c33f21daad0b4e56a200";
            } else if ($architecturalStyle == "Tri-level") {
                $typeId = "5f28c3b7ce2bd979cf3a6197";
            } else if ($architecturalStyle == "Multi-level") {
                $typeId = "5f28c3c6024da6d25f6ebcb7";
            } else {
                $stories = floatval("{$building->StoriesTotal}");
                if ($stories == 1) {
                    $typeId = "5f28c3c6024da6d25f6ebcb7";
                } else if ($stories == 1.25) {
                    $typeId = "5f28c35790ccd298a0237b25";
                } else if ($stories == 1.5) {
                    $typeId = "5f28c360a5b59b53e9dbb0dd";
                } else if ($stories == 1.75) {
                    $typeId = "5f28c368fc8d4a11b39c677f";
                } else if ($stories == 2) {
                    $typeId = "5f28c36de051c60c8c863e93";
                } else if ($stories == 2.25) {
                    $typeId = "5f28c375552e684c25806704";
                } else if ($stories == 2.5) {
                    $typeId = "5f28c38399f78159520e5fd5";
                } else if ($stories == 2.75) {
                    $typeId = "5f28c38c327806ecabd9db4e";
                }
            }
        }
    }


    return $typeId;
}

function get_price_range($price)
{
    $priceId = FALSE;

    $price = intval($price);
    if ($price < 250000) {
        $priceId = "5f28c2b6397ee38a604aa381";
    } else if ($price < 350000) {
        $priceId = "5f28c2c0a98f21369d78de56";
    } else if ($price < 450000) {
        $priceId = "5f28c2d520520b83b7b32f23";
    } else if ($price < 600000) {
        $priceId = "5f28c2e0024da61ac66ebcb6";
    } else if ($price < 1000000) {
        $priceId = "5f28c2f10943538634ee889e";
    } else {
        $priceId = "5f28c306b7311748f18a763a";
    }

    return $priceId;
}
