<?php
require_once('CourierManagerApi.php');

$config = require_once('config.php');
$apiClient = new CourierManagerApi($config['apiUrl'], $config['apiKey']);

$apiData = array();

// Delivery details
if (isset($_GET['type']) && ($_GET['type'] == "getPrice" || $_GET['type'] == "getAwb")) {
    $fields = array(
        'contactName',
        'phone',
        'email',
        'quantity',
        'packageType',
        'serviceType',
        'weight',
        'toCountry',
        'toCounty',
        'toCity',
        'toAddress',
        'zipCode',
        'cashOnDelivery',
        'cashOnDeliveryType'
    );
    $values = array();
    $errors = array();
    $deliveryValues = array();

    $packageTypes = array('envelope','package');
    $cashOnDeliveryTypes = array('cash','cont');

    $deliveryServices = $apiClient->getServicesList();
    foreach ($deliveryServices as $deliveryService){
        $deliveryValues[$deliveryService->name] = $deliveryService->value;
    }

    foreach ($fields as $field) {
        if (!isset($_POST[$field])) {
            $values[$field] = '';
            continue;
        }

        $values[$field] = $_POST[$field];
    }

    if (!isset($values['contactName'])) {
        $errors['contactName'] = "Contact name is required";
    } elseif (strlen($values['contactName']) < 2 || strlen($values['contactName']) > 100){
        $errors['contactName'] = "Contact name must be between 2 and 100 characters";
    }

    if(!isset($values['phone'])) {
        $errors['phone'] = "Phone number is required";
    } elseif (strlen($values['phone']) < 5 || strlen($values['phone']) > 15) {
        $errors['phone'] = "Phone number must be between 5 and 15 characters";
    }

    if (!in_array($values['packageType'], $packageTypes)){
        $errors['packageType'] = 'Package type must be envelope or package!';
    }

    if (!in_array($values['serviceType'], $deliveryValues)){
        $errors['serviceType'] = 'Delivery type must be one standard or express!';
    }

    if(!isset($values['quantity'])) {
        $errors['quantity'] = "Quantity is required";
    } elseif (!preg_match('/^[0-9]*$/', $values['quantity'])){
        $errors['quantity'] = "Quantity must be a valid number!";
    }

    if (!isset($values['toAddress'])) {
        $errors['toAddress'] = 'Address is required';

    } elseif (strlen($values['toAddress']) < 5 || strlen($values['toAddress']) > 300) {
        $errors['toAddress'] = 'Address must be between 5 and 300 characters';
    }

    if(!isset($values['zipCode'])) {
        $errors['zipCode'] = "Zip code is required";
    } elseif (strlen($values['zipCode']) < 3 || strlen($values['zipCode']) > 15){
        $errors['zipCode'] = "Zip must be between 3 and 15 characters";
    }

    if (!in_array($values['cashOnDeliveryType'], $cashOnDeliveryTypes)) {
        $errors['cashOnDeliveryType'] = 'COD payment type must be cash or card!';
    }

    // API call data
    $apiData = array(
        "to_contact" => $values['contactName'],
        "to_phone" => $values['phone'],
        "to_email" => $values['email'],
        "use_default_from_address" => "true",
        "type" => $values['packageType'],
        "service_type" => $values['serviceType'],
        "cnt" => $values['quantity'],
        "weight" => $values['weight'],
        "to_country" => $values['toCountry'],
        "to_county" => $values['toCounty'],
        "to_city" => $values['toCity'],
        "to_address" => $values['toAddress'],
        "to_zipcode" => $values['zipCode'],
        "ramburs" => $values['cashOnDelivery'],
        'ramburs_type' => $values['cashOnDeliveryType']
    );

    if(count($errors) > 0){
        http_response_code(400);
        die(json_encode($errors));
    }
}

// Get price for AWB
if (isset($_GET['type']) && $_GET['type'] == "getPrice") {
    $apiPrice = $apiClient->priceAwb($apiData);

    // Check if response is ok
    if (!isset($apiPrice->status) || $apiPrice->status != "done") {
        http_response_code(400);
        die(json_encode(array(
            'request' => $apiPrice->message
        )));
    }

    $taxes = $apiPrice->data->price * (  $config['tva'] / 100 );

    // Return delivery price
    die(json_encode(array(
        'price' => $apiPrice->data->price + $taxes
    )));
}

// Generate AWB
if (isset($_GET['type']) && $_GET['type'] == "getAwb") {
    $awb = $apiClient->createAwb($apiData);
    $hash = hash_hmac('ripemd160', $awb->data->no, $config['apiKey']);
    die(json_encode(array(
        'id' => $awb->data->no,
        'hash' => $hash,
    )));
}

// Get AWB PDF
if (isset($_GET['type']) && $_GET['type'] == "printAwb" &&
    isset($_GET['id']) && is_numeric($_GET['id']) &&
    isset($_GET['hash']) && is_string($_GET['hash'])) {
    $hash = hash_hmac('ripemd160', $_GET['id'], $config['apiKey']);
    if ($hash !== $_GET['hash']) {
        die("Permission denied.");
    }

    $awb = $apiClient->printAwb($_GET['id']);
    header("Content-type:application/pdf");
    header("Content-Disposition:attachment;filename=awb-{$_GET['id']}.pdf");
    die($awb);
}

// Setup app
if (isset($_GET['type']) && $_GET['type'] == "bootstrap") {
    $data = array(
        "services" => $apiClient->getServicesList(),
        "currency" => $config['currency']
    );
    die(json_encode($data));
}
