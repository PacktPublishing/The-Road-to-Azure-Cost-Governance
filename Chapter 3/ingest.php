<?php

$db_host="localhost";
$db_user="root";
$db_pass="dba";
$db_name="azure_costs";

// the name of the temporary database table for costs
$db_csvcost_victim_table="az_billing_victim";
// the name of the final database table for costs
$db_csvcost_table="az_billing_details";
// the name of the final table with the splitted costs by BusinessApplication
$db_splittedcosts_table="az_billing_splitted";


// == TAGS ==
// TODO : CUSTOMIZE TO YOUR NEEDS
// tag that identify business application associated to the resource 
// example   BsnApp=|1234|      for one application
// example   BsnApp=|1234|7890| for two applications
$tag_bsnapp="BsnApp";
$tag_bsnapp_separator="|";
// tag that identify environment/landscape (production,quality,development...)
$tag_landscape="Landscape";



// take full csv path from command line
$azure_costs_csv_file="";
if ( !isset($argv[1]) ){
  echo "USAGE : ".$argv[0]." <path to azure costs csv file>\r\n";
  return 9;
}else{
  $azure_costs_csv_file=$argv[1];
}


// connection to the database
$link = mysqli_connect( $db_host, $db_user, $db_pass, $db_name);
if (!$link) {
  $err=mysqli_connect_errno();
  echo "ERROR : unable to connect to db server or db ".$db_name." (".$err.")\r\n";
  return 1;
}

// let's be sure we import the csv in an empty table
$sql_truncate_victim="truncate table ".$db_csvcost_victim_table.";";
if(mysqli_query($link, $sql_truncate_victim)){
    echo "INFO : victim table truncated successfully\r\n";
} else{
    echo "ERROR : Could not truncate table ".$db_csvcost_victim_table." (".mysqli_error($link).")\r\n";
    return 2;
}

// load CSV into victim table
$sql_import_csv="LOAD DATA INFILE \"".$azure_costs_csv_file."\" 
			INTO TABLE ".$db_csvcost_victim_table."
			COLUMNS TERMINATED BY ',' 
			OPTIONALLY ENCLOSED BY '\"' 
			LINES TERMINATED BY '\n' 
			IGNORE 1 LINES;";

if(mysqli_query($link, $sql_import_csv)){
    echo "INFO : Azure csv costs file succesfully imported\r\n";
} else{
    echo "ERROR : Could not execute $sql_import_csv (".mysqli_error($link).")\r\n";
    return 3;
}

$sql_get_victim_data="select * from ".$db_csvcost_victim_table.";";
$result = mysqli_query($link, $sql_get_victim_data);
if (mysqli_num_rows($result) > 0) { 
  // for each cost row in the victim table  : 
  // 1. identify tags for shared resources
  // 2. split costs
  // 3. normalize date in iso YYYY-MM-DD
  // 4. insert records 
  while($row = mysqli_fetch_assoc($result)) {
   
    //TODO : customize rule to normalize date
    //       this is an example from italian format DD/MM/YYYY to ISO
    $mydate=substr($row["Date"],6,4)."-".substr($row["Date"],0,2)."-".substr($row["Date"],3,2);
    $tags=$row["Tags"];

    $landscape="";
    $bsnapps="";

    // tags contain a json with, for example : ,""Landscape"": ""Production""",
    // we should extract the value from the key/value.
    if ( strpos($tags,$tag_landscape)>0 ){
      $landscape=substr($tags,strpos($tags,$tag_landscape)+strlen($tag_landscape)+4);
      $landscape=substr($landscape,0,strpos($landscape,"\""));
    }

    // tags contain a json with, for example : ,""KApp"": ""|616|""",
    // we should extract the value from the key/value.
    if ( strpos($tags,$tag_bsnapp)>0 ){
      $bsnapps=substr($tags,strpos($tags,$tag_bsnapp_separator,strpos($tags,$tag_bsnapp)+1));
      $bsnapps=substr($bsnapps,0,strpos($bsnapps,"\""));
    }
      
    echo "CSV Date: " . $row["Date"]. " - ISO Date: ".$mydate." - Resource: ".$row["ResourceName"]." - Landscape:".$landscape." - BsnApps: ".$bsnapps."\r\n";

    // insert into final azure cost table
    $sql = "INSERT INTO ".$db_csvcost_table." VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt= mysqli_prepare($link,$sql);
    mysqli_stmt_bind_param($stmt,"sssssssssssssssssssddddssssssssssssssssssssssssssssss", $row["BillingAccountId"],$row["BillingAccountName"],$row["BillingPeriodStartDate"],$row["BillingPeriodEndDate"],$row["BillingProfileId"],$row["BillingProfileName"],$row["AccountOwnerId"],$row["AccountName"],$row["SubscriptionId"],$row["SubscriptionName"],$mydate,$row["Product"],$row["PartNumber"],$row["MeterId"],$row["ServiceFamily"],$row["MeterCategory"],$row["MeterSubCategory"],$row["MeterRegion"],$row["MeterName"],$row["Quantity"],$row["EffectivePrice"],$row["Cost"],$row["UnitPrice"],$row["BillingCurrency"],$row["ResourceLocation"],$row["AvailabilityZone"],$row["ConsumedService"],$row["ResourceId"],$row["ResourceName"],$row["ServiceInfo1"],$row["ServiceInfo2"],$row["AdditionalInfo"],$row["Tags"],$row["InvoiceSectionId"],$row["InvoiceSection"],$row["CostCenter"],$row["UnitOfMeasure"],$row["ResourceGroup"],$row["ReservationId"],$row["ReservationName"],$row["ProductOrderId"],$row["ProductOrderName"],$row["OfferId"],$row["IsAzureCreditEligible"],$row["Term"],$row["PublisherName"],$row["PlanName"],$row["ChargeType"],$row["Frequency"],$row["PublisherType"],$row["PayGPrice"],$row["PricingModel"],$row["CostAllocationRuleName"]);
    mysqli_stmt_execute($stmt);


    // for cost splitting we need to insert one record for each bsnapp in the splitted cost table
    // let's split for the separator
    $a_bsnapps=explode($tag_bsnapp_separator,$bsnapps);

    // let's exclude invalid, empty bsnapps
    $total_bsnapps=0;
    for ($b=0; $b<count($a_bsnapps);$b++){
      if (strlen($a_bsnapps[$b])>=1)
        $total_bsnapps++;
    }


    // let's split in a mathematical way
    $mycost=$row["Cost"];
    if ($total_bsnapps>1){
      $mycost=$row["Cost"]/$total_bsnapps;
    }

    if (strpos($bsnapps,$tag_bsnapp_separator)!==false){
      for ($b=0; $b<count($a_bsnapps);$b++){
        if (strlen($a_bsnapps[$b])>=1){
          $bsnapp=$a_bsnapps[$b];
          // insert into final azure splitted costs table
          $sql = "INSERT INTO ".$db_splittedcosts_table." VALUES (?,?,?,?,?,?,?,?,?,?,?)";
          $stmt= mysqli_prepare($link,$sql);
          mysqli_stmt_bind_param($stmt,"sssssssssdd",$mydate,$bsnapp,$bsnapps,$landscape,$row["ResourceId"],$row["ResourceGroup"],$row["MeterCategory"],$row["MeterSubCategory"],$row["MeterName"],$mycost,$row["Cost"]);
  	  mysqli_stmt_execute($stmt);
        }	
      }
    }else{
      //if i don't have a tag for the resource i need to insert it anyway in the splitted cost table
      //otherwise i'll lose costs and the splitted costs sum will never be equal to the invoice.
      $sql = "INSERT INTO ".$db_splittedcosts_table." VALUES (?,?,?,?,?,?,?,?,?,?,?)";
      $stmt= mysqli_prepare($link,$sql);
      $bsnapp=""; 
      mysqli_stmt_bind_param($stmt,"sssssssssdd",$mydate,$bsnapp,$bsnapps,$landscape,$row["ResourceId"],$row["ResourceGroup"],$row["MeterCategory"],$row["MeterSubCategory"],$row["MeterName"],$mycost,$row["Cost"]);
      mysqli_stmt_execute($stmt);
    }
  }
} else {
  echo "ERROR : no result. Please verify CSV file and column number and type.";
  return 4;	
}

mysqli_close($link);
return 0;
?>


