<?php

require_once("phrets.php");
require_once("webflow.php");

error_reporting(E_ERROR);
ini_set("error_log", "file.log");
ini_set('max_execution_time', 60 * 60);


$property_collection  = get_id('collections', 'Property Listings');
if (!$property_collection) {
    return;
}
$properties_num = get_properties($property_collection);
if ($properties_num < 1) {
    die('Failed to get properties');
}

$city_collection  = get_id('collections', 'Cities');
if (!$city_collection) {
    return;
}
$cities_num = get_items($city_collection, 'cities');
if ($cities_num < 1) {
    die('Failed to get cities');
}

$price_collection  = get_id('collections', 'Price Ranges');
if (!$price_collection) {
    return;
}

$type_collection  = get_id('collections', 'Property Types');
if (!$type_collection) {
    return;
}

$status_collection  = get_id('collections', 'Listing Statuses');
if (!$status_collection) {
    return;
}


get_properties($property_collection);

$login = 'http://data.crea.ca/Login.svc/Login';
$un = '4Upb5e1Gg7fL2TbFbG6ZTdae';
$pw = 'NbnDRKPsUUe2YOxgrHoHrK1F';

$rets = new PHRETS;

$connect = $rets->Connect($login, $un, $pw);

// http://data.crea.ca/Search.svc/Search?Format=Standard-XML&SearchType=Property&Class=Property&QueryType=DMQL2&Query=(ID=*)&Count=1

/* Query Server */
if ($connect) {

    $search = $rets->SearchQuery(
        'Property',                                // Resource
        'Property',                                // Class
        '(ID=*)',    // DMQL, with SystemNames
        array(
            'Format'    => 'Standard-XML',
            'Count'        => 1,
            'Limit'     => 100
        )
    );

    $properties = [];
    $_properties = [];

    /* If search returned results */
    if ($rets->TotalRecordsFound() > 0) {

        while ($propertyId = $rets->FetchRow($search)) {

            print($propertyId . "\n");

            $property = $rets->SearchDetail($propertyId);
            $_properties[] = $property;

            $listingId = "{$property->ListingID}";
            $board = "{$property->Board}";
            $features = "{$property->Features}";
            $price = intval("{$property->Price}");
            $remarks = "{$property->PublicRemarks}";
            $transactionType = "{$property->TransactionType}";

            $address = $property->Address;
            $streetAddress = "{$address->StreetAddress}";
            $city = "{$address->City}";
            $province = "{$address->Province}";
            $postalCode = "{$address->PostalCode}";

            $dir = 'photos/' . $propertyId;
            if (!file_exists($dir)) {
                mkdir($dir);
            }

            $photos = [];
            $_photos = $property->Photo->PropertyPhoto;
            foreach ($_photos as $photo) {

                $photoUrl = "{$photo->LargePhotoURL}";
                $photos[] = $photoUrl;

                /*$photoFile = $dir . '/' . $photo->SequenceId . '.jpg';
                if (file_exists($photoFile)) {
                    continue;
                }

                $img = file_get_contents($photoUrl);
                file_put_contents($photoFile, $img);*/
            }

            $building = $property->Building;
            $bathrooms = "{$building->BathroomTotal}";
            $bedrooms = "{$building->BedroomsTotal}";
            $_sqft = "{$building->SizeInterior}";

            $sqft = 0;
            if (!empty($_sqft)) {
                $_sqft = trim(str_replace('sqft', '', $_sqft));
                $sqft = intval($_sqft);
            }

            $agent = "{$property->AgentDetails->Name}";

            $cityId = get_city($city);
            if (empty($cityId)) {
                error_log('Missing city: ' . $city);
                $cityId = insert_city($city_collection, $city);

                if (empty($cityId)) {
                    continue;
                }

                insert_table('cities', [
                    'id' => $cityId,
                    'name' => $city,
                    'slug' => slugify($city),
                    'cid' => $city_collection
                ]);
            }

            $transId = get_trans_type($transactionType);
            $typeId = get_prop_type($property);
            $priceId = get_price_range($price);

            $_remarks = explode("/" . $city . "/", $remarks);
            $_remarks = $_remarks[1];
            $_remarks = explode("(id:", $_remarks);
            $_remarks = $_remarks[0];
            $description = trim($_remarks);

            $_property = [
                'cityId' => $cityId,
                'statusId' => $transId,
                'typeId' => $typeId,
                'priceId' => $priceId,

                'listingId' => $listingId,
                'board' => $board,
                'features' => $features,
                'bathrooms' => $bathrooms,
                'bedrooms' => $bedrooms,
                'sqft' => $sqft,
                'price' => $price,
                'remarks' => $description,
                'transactionType' => $transactionType,
                'photos' => $photos,
                'agent' => $agent,
                'streetAddress' => $streetAddress,
                'city' => $city,
                'province' => $province,
                'postalCode' => $postalCode,
            ];

            $properties[] = $_property;
        }
    }

    $rets->Disconnect();
} else {
    $error = $rets->Error();
    die($error);
}


if (count($properties) == 0) {
    die('No properties');
}

// file_put_contents('properties.json', json_encode($_properties));
error_log('Total RETS properties - ' . count($properties));

$dbProperties = select_table('properties');

$RETS_count = count($properties);
$added = 0;
$updated = 0;
$deleted = 0;

// Loop properties
foreach ($dbProperties as $dbProperty) {

    // If not exists, then remove
    $bRemove = true;
    foreach ($properties as $property) {
        if ($property['listingId'] == $dbProperty['mls-r-number']) {
            $bRemove = false;
            break;
        }
    }

    if ($bRemove) {
        $deleted++;
        error_log('Delete property - ' . $dbProperty['id']);
        delete_property($property_collection, $dbProperty['id']);
        $deleted++;
        sleep(1);
    } else {
        // Update property
        error_log('Update property - ' . $dbProperty['id']);
        $resp = update_property($property_collection, $property, $dbProperty);
        if ($resp) {
            $updated++;
        }
        sleep(1);
    }
}

foreach ($properties as $property) {

    // If not exists, then remove
    $bNew = true;
    foreach ($dbProperties as $dbProperty) {
        if ($property['listingId'] == $dbProperty['mls-r-number']) {
            $bNew = false;
            break;
        }
    }

    if ($bNew) {
        error_log('Insert property - ' . $property['listingId']);

        $added++;
        $resp = insert_property($property_collection, $property);
        if ($resp) {
            $updated++;
        }
        else {
        }

        sleep(1);
    }
}

publish_site();

insert_table('logs', [
    'created_at' => date('Y-m-d H:i:s'),
    'rets_count' => $RETS_count,
    'added' => $added,
    'updated' => $updated,
    'deleted' => $deleted,
    'errors' => $RETS_count - $added - $updated - $deleted,
]);
