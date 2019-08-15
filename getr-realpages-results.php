$xml_data = '<?xml version="1.0" encoding="UTF-8" standalone="no"?><SOAP-ENV:Envelope
    xmlns:SOAP-ENV="http://www.w3.org/2003/05/soap-envelope" xmlns:s="http://www.w3.org/2001/XMLSchema"
    xmlns:soap12="http://schemas.xmlsoap.org/wsdl/soap12/" xmlns:http="http://schemas.xmlsoap.org/wsdl/http/"
    xmlns:mime="http://schemas.xmlsoap.org/wsdl/mime/" xmlns:tns="http://realpage.com/webservices"
    xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/" xmlns:tm="http://microsoft.com/wsdl/mime/textMatching/"
    xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <SOAP-ENV:Header>
        <tns:UserAuthInfo xmlns:tns="http://realpage.com/webservices">
            <tns:UserName>USER</tns:UserName>
            <tns:Password>PASSWORD</tns:Password>
            <tns:SiteID>SITEID</tns:SiteID>
            <tns:PmcID>PMCID</tns:PmcID>
            <tns:InternalUser></tns:InternalUser>
        </tns:UserAuthInfo>
        <tns:CallBackAuthInfo xmlns:tns="http://realpage.com/webservices">
            <tns:App></tns:App>
            <tns:SessionID></tns:SessionID>
            <tns:SiteID>SITEID</tns:SiteID>
            <tns:PmcID>PMCID</tns:PmcID>
            <tns:EncryptionKey></tns:EncryptionKey>
        </tns:CallBackAuthInfo>
    </SOAP-ENV:Header>
    <SOAP-ENV:Body>
        <tns:List xmlns:tns="http://realpage.com/webservices">
            <tns:listCriteria>
                <tns:ListCriterion>
                        <tns:Name>DateNeeded</tns:Name>
                        <tns:SingleValue>' . $move_in_date . '</tns:SingleValue>
                        <tns:MinValue></tns:MinValue>
                        <tns:MaxValue></tns:MaxValue>            
                </tns:ListCriterion>
                <tns:ListCriterion>
                        <tns:Name>LimitResults</tns:Name>
                        <tns:SingleValue>false</tns:SingleValue>
                        <tns:MinValue></tns:MinValue>
                        <tns:MaxValue></tns:MaxValue>            
                </tns:ListCriterion>                
                <tns:ListCriterion>
                        <tns:Name>NumberBathrooms</tns:Name>
                        <tns:SingleValue>'. $bathrooms .'</tns:SingleValue>
                        <tns:MinValue></tns:MinValue>
                        <tns:MaxValue></tns:MaxValue>            
                </tns:ListCriterion>
                <tns:ListCriterion>
                        <tns:Name>NumberBedrooms</tns:Name>
                        <tns:SingleValue>'. $bedrooms .'</tns:SingleValue>
                        <tns:MinValue></tns:MinValue>
                        <tns:MaxValue></tns:MaxValue>            
                </tns:ListCriterion>                                                  
            </tns:listCriteria>
        </tns:List>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

$URL = "http://onesite.realpage.com/WebServices/CrossFire/AvailabilityAndPricing/Unit.asmx";

$ch = curl_init($URL);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, "$xml_data");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$output = curl_exec($ch);
curl_close($ch);


// Get SOAP results... 
$soap = simplexml_load_string($output);
$response = $soap->children('soap', true)->Body->children()->ListResponse;
$results = $response->ListResult;

// Create an array to push results 
$resultObject = array(); 

// Money Format because windows doesn't allow money format
// @ param  $value      the float you want to format
function format_currency_dollars($value) {
    return '$' . number_format($value, 0);
}


foreach($results->UnitObject as $result){
    if($floor == $result->UnitDetails->FloorNumber || $floor == ''){
        if($rent_min <= $result->FloorPlanMarketRent && $result->FloorPlanMarketRent <= $rent_max){

            // Price
            $money = (float) $result->FloorPlanMarketRent;
            $formatted_money = format_currency_dollars($money);

            // Availability
            if($result->Availability->VacantBit == true){
                $availability = 'Now';
            }else{
                $availability = 'Later';
            }

            // Format the bedroom
            if($result->UnitDetails->Bedrooms == '0'){
                $bedrooms_formatted = 'Studio';
                $bedrooms_formatted_noBed = 'Studio';
            }else{
                $bedrooms_formatted = (string) $result->UnitDetails->Bedrooms . ' Bed';
                $bedrooms_formatted_noBed = (string) $result->UnitDetails->Bedrooms;
            }            

            // Check for files in folder and set as array
            $files = glob('floorplan_images/' . $result->FloorPlan->FloorPlanCode . '/*.{jpg,png,gif}', GLOB_BRACE);

            // Get files and append relative path 
            foreach ($files as &$file) {
                $file = '/wp-content/themes/FoundationPress/realpages/' . $file;
            }
            unset($file);

            // Set array to use in front end
            $resultArrayItem = array(
                'unit' => (string) $result->Address->UnitNumber,
                'bed' => (int) $result->UnitDetails->Bedrooms,
                'bed_formated' => $bedrooms_formatted,
                'bed_formated_no_bed' => $bedrooms_formatted_noBed,
                'bath' => (int) $result->UnitDetails->Bathrooms,
                'sqft' => (int) $result->UnitDetails->GrossSqFtCount,
                'floor' => (int) $result->UnitDetails->FloorNumber,
                'price' => (int) $result->FloorPlanMarketRent, 
                'price_formatted' => $formatted_money, 
                'availability' => $availability,
                'foorplan_name' => (string) $result->FloorPlan->FloorPlanCode,
                'floor_plan_images' => (object) $files
            );

            array_push($resultObject, $resultArrayItem );
        }
    }
}

// Return json object
$resultArrayObject = (object) $resultObject;
$json = json_encode($resultArrayObject);
echo $json;
