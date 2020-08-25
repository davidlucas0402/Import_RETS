<?php

require_once("phrets.php");
ini_set("error_log", "file.log");

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

    /* If search returned results */
    if ($rets->TotalRecordsFound() > 0) {

        $properties = [];

        while ($propertyId = $rets->FetchRow($search)) {

            error_log($propertyId);

            $property = $rets->SearchDetail($propertyId);

            $listingId = "{$property->ListingID}";
            $board = "{$property->Board}";
            $features = "{$property->Features}";
            $price = "{$property->Price}";
            $propertyType = "{$property->PropertyType}";
            $remarks = "{$property->PublicRemarks}";
            $transactionType = "{$property->TransactionType}";

            $dir = 'photos/' . $propertyId;
            if (!file_exists($dir)) {
                mkdir($dir);
            }

            $photos = [];
            $_photos = $property->Photo->PropertyPhoto;
            foreach ($_photos as $photo) {

                $photoUrl = "{$photo->LargePhotoURL}";
                $photos[] = $photoUrl;

                $photoFile = $dir . '/' . $photo->SequenceId . '.jpg';
                if (file_exists($photoFile)) {
                    continue;
                }

                $img = file_get_contents($photoUrl);
                file_put_contents($photoFile, $img);
            }

            $properties[] = [
                'listingId' => $listingId,
                'board' => $board,
                'features' => $features,
                'price' => $price,
                'propertyType' => $propertyType,
                'remarks' => $remarks,
                'transactionType' => $transactionType,
                'photos' => $photos
            ];

            break;
        }

        echo json_encode($properties);
    } else {
        echo json_encode([]);
    }

    $rets->Disconnect();
} else {
    $error = $rets->Error();
    echo json_encode([]);
}
