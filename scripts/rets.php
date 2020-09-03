<?php

date_default_timezone_set('America/Chicago');

require_once("phrets.php");
require_once("webflow.php");

error_reporting(E_ERROR);
ini_set("error_log", "file.log");
ini_set('max_execution_time', 60 * 60);


get_collections(WEBFLOW_SITEID);

$property_collection  = get_id('collections', 'Listings');
if (!$property_collection) {
    return;
}

$city_collection  = get_id('collections', 'Cities');
if (!$city_collection) {
    return;
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

$properties_num = get_properties($property_collection);
if ($properties_num < 1) {
    die('Failed to get properties');
}
sleep(1);

$city_count = 0;
for ($i = 0; $i < 5; $i++) {
    $city_count = get_items($city_collection, 'cities');
    if ($city_count > 0) {
        break;
    }
    sleep(5);
}

if($city_count < 1) {
    die('City not working.');
}

sleep(1);

get_items($price_collection, 'price_ranges');
sleep(1);

get_items($type_collection, 'property_types');
sleep(1);

get_items($status_collection, 'listing_status');
sleep(1);

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

    $_properties = [];
    $properties = [];

    /* If search returned results */
    if ($rets->TotalRecordsFound() > 0) {

        while ($propertyId = $rets->FetchRow($search)) {

            print($propertyId . "\n");

            $property = $rets->SearchDetail($propertyId);
            $_properties[] = $property;

            $lastUpdated = $property->{'@attributes'}->LastUpdated;

            $listingId = "{$property->ListingID}";
            $board = "{$property->Board}";
            $features = "{$property->Features}";
            $price = intval("{$property->Price}");
            $remarks = "{$property->PublicRemarks}";
            $transactionType = "{$property->TransactionType}";
            $ownershipType = "{$property->OwnershipType}";

            $address = $property->Address;
            $streetAddress = "{$address->StreetAddress}";
            $city = "{$address->City}";
            $province = "{$address->Province}";
            $postalCode = "{$address->PostalCode}";
            $neighbourhood = "{$address->Neighbourhood}";

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

            $transId = get_trans_type($ownershipType);
            $typeId = get_prop_type($property);
            $priceId = get_price_range($price);

            $_remarks = explode("/" . $city . "/", $remarks);
            $_remarks = $_remarks[1];
            $_remarks = explode("(id:", $_remarks);
            $_remarks = $_remarks[0];
            $description = trim($_remarks);

            $alternateURL = $property->AlternateURL;

            $videoLink = '';
            if (isset($alternateURL->VideoLink)) {
                $videoLink = $alternateURL->VideoLink;
            }

            $_property = [

                'lastUpdated' => $lastUpdated,

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
                'videoLink' => $videoLink,
                'neighbourhood' => $neighbourhood,
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
        sleep(2);
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
        } else {
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
