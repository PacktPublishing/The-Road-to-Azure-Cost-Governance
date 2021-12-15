<?php

$db_host="localhost";
$db_user="root";
$db_pass="dba";

// new database to be created
$db_name="azure_costs";

// the name of the temporary database table for costs
$db_csvcost_victim_table="az_billing_victim";
// the name of the final database table for costs
$db_csvcost_table="az_billing_details";
// the name of the final table with the splitted costs by BusinessApplication
$db_splittedcosts_table="az_billing_splitted";

$link = mysqli_connect( $db_host, $db_user, $db_pass);
if (!$link) {
  $err=mysqli_connect_errno();
  echo "ERROR : unable to connect to db (".$err.")\r\n";
  return 2;
}

//create database
$sql_dbcreate = "CREATE DATABASE ".$db_name;
if(mysqli_query($link, $sql_dbcreate)){
    echo "INFO : Database created successfully\r\n";
} else{
    echo "ERROR : Could not able to execute $sql_dbcreate (".mysqli_error($link).")\r\n";
}

// select the newly created db
$db_selected = mysqli_select_db( $link, $db_name );
if (!$db_selected) {
  echo "ERROR : unable to use ".$db_name." database (".mysqli_error($link).")";
  return 3;
}

//create a table for the Azure cost 1:1 with the fields from the CSV file.
$sql_victimcreate = "CREATE table ".$db_csvcost_victim_table." (
			BillingAccountId varchar(20) not null default '',
			BillingAccountName varchar(100) not null default '',
			BillingPeriodStartDate varchar(10) not null default '',
			BillingPeriodEndDate varchar(10) not null default '',
			BillingProfileId varchar(20) not null default '',
			BillingProfileName varchar(100) not null default '',
			AccountOwnerId varchar(100) not null default '',
			AccountName varchar(100) not null default '',
			SubscriptionId varchar(100) not null default '',
			SubscriptionName varchar(100) not null default '',
			Date varchar(10) not null default '',
			Product varchar(120) not null default '',
			PartNumber varchar(100) not null default '',
			MeterId varchar(50) not null default '',
			ServiceFamily varchar(50) not null default '',
			MeterCategory varchar(60) not null default '',
			MeterSubCategory varchar(60) not null default '',
			MeterRegion varchar(50) not null default '',
			MeterName varchar(50) not null default '',
			Quantity double not null default 0,
			EffectivePrice double not null default 0,
			Cost double not null default 0,
			UnitPrice varchar(50) not null default '',
			BillingCurrency varchar(10) not null default '',
			ResourceLocation varchar(50) not null default '',
			AvailabilityZone varchar(50) not null default '',
			ConsumedService varchar(100) not null default '',
			ResourceId  varchar(2000) not null default '',
			ResourceName varchar(500) not null default '',
			ServiceInfo1 varchar(500) not null default '',
			ServiceInfo2 varchar(500) not null default '',
			AdditionalInfo varchar(2000) not null default '',
			Tags varchar(3000) not null default '',
			InvoiceSectionId varchar(100) not null default '',
			InvoiceSection varchar(100) not null default '',
			CostCenter varchar(100) not null default '',
			UnitOfMeasure varchar(100) not null default '',
			ResourceGroup varchar(200) not null default '',
			ReservationId varchar(2000) not null default '',
			ReservationName varchar(500) not null default '',
			ProductOrderId varchar(1000) not null default '',
			ProductOrderName varchar(100) not null default '',
			OfferId varchar(100) not null default '',
			IsAzureCreditEligible varchar(100) not null default '',
			Term varchar(100) not null default '',
			PublisherName varchar(100) not null default '',
			PlanName varchar(100) not null default '',
			ChargeType varchar(100) not null default '',
			Frequency varchar(100) not null default '',
			PublisherType varchar(100) not null default '',
			PayGPrice varchar(100) not null default '',
			PricingModel varchar(100) not null default '',
			CostAllocationRuleName varchar(500) not null default ''
		)";


if(mysqli_query($link, $sql_victimcreate)){
  echo "INFO : victim table ".$db_csvcost_victim_table." created succesfully\r\n";
} else{
  echo "ERROR : Could not able to execute $sql_victimcreate (".mysqli_error($link).")\r\n";
  return 4;
}


//create a table for the Azure elaborated csv costs.
$sql_costcreate = "CREATE table ".$db_csvcost_table." (
			BillingAccountId varchar(20) not null default '',
			BillingAccountName varchar(100) not null default '',
			BillingPeriodStartDate varchar(10) not null default '',
			BillingPeriodEndDate varchar(10) not null default '',
			BillingProfileId varchar(20) not null default '',
			BillingProfileName varchar(100) not null default '',
			AccountOwnerId varchar(100) not null default '',
			AccountName varchar(100) not null default '',
			SubscriptionId varchar(100) not null default '',
			SubscriptionName varchar(100) not null default '',
			Date date not null default '0000-00-00',
			Product varchar(120) not null default '',
			PartNumber varchar(100) not null default '',
			MeterId varchar(50) not null default '',
			ServiceFamily varchar(50) not null default '',
			MeterCategory varchar(60) not null default '',
			MeterSubCategory varchar(60) not null default '',
			MeterRegion varchar(50) not null default '',
			MeterName varchar(50) not null default '',
			Quantity double not null default 0,
			EffectivePrice double not null default 0,
			Cost double not null default 0,
			UnitPrice varchar(50) not null default '',
			BillingCurrency varchar(10) not null default '',
			ResourceLocation varchar(50) not null default '',
			AvailabilityZone varchar(50) not null default '',
			ConsumedService varchar(100) not null default '',
			ResourceId  varchar(2000) not null default '',
			ResourceName varchar(500) not null default '',
			ServiceInfo1 varchar(500) not null default '',
			ServiceInfo2 varchar(500) not null default '',
			AdditionalInfo varchar(2000) not null default '',
			Tags varchar(3000) not null default '',
			InvoiceSectionId varchar(100) not null default '',
			InvoiceSection varchar(100) not null default '',
			CostCenter varchar(100) not null default '',
			UnitOfMeasure varchar(100) not null default '',
			ResourceGroup varchar(200) not null default '',
			ReservationId varchar(2000) not null default '',
			ReservationName varchar(500) not null default '',
			ProductOrderId varchar(1000) not null default '',
			ProductOrderName varchar(100) not null default '',
			OfferId varchar(100) not null default '',
			IsAzureCreditEligible varchar(100) not null default '',
			Term varchar(100) not null default '',
			PublisherName varchar(100) not null default '',
			PlanName varchar(100) not null default '',
			ChargeType varchar(100) not null default '',
			Frequency varchar(100) not null default '',
			PublisherType varchar(100) not null default '',
			PayGPrice varchar(100) not null default '',
			PricingModel varchar(100) not null default '',
			CostAllocationRuleName varchar(500) not null default ''
		)";


if(mysqli_query($link, $sql_costcreate)){
  echo "INFO : victim table ".$db_csvcost_table." created succesfully\r\n";
} else{
  echo "ERROR : Could not able to execute $sql_costcreate (".mysqli_error($link).")\r\n";
  return 4;
}

$sql_splittedcosts="CREATE table ".$db_splittedcosts_table." ( 
	                Date date not null default '0000-00-00',
			BsnApp varchar(20) not null default '',
			BsnApp_Tag varchar(200) not null default '',
			Landscape varchar(200) not null default '',
			ResourceId varchar(2000) not null default '',
                        ResourceGroup varchar(200) not null default '',
			MeterCategory varchar(60) not null default '',
			MeterSubCategory varchar(60) not null default '',
			MeterName varchar(50) not null default '',
			SplittedCost double not null default 0,
                        FullCost double not null default 0
		        )";

if(mysqli_query($link, $sql_splittedcosts)){
  echo "INFO : victim table ".$db_splittedcosts_table." created succesfully\r\n";
} else{
  echo "ERROR : Could not able to execute $sql_splittedcosts (".mysqli_error($link).")\r\n";
  return 4;
}


mysqli_close($link);
return 0;
?>


