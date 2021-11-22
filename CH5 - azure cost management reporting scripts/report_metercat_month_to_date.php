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


// we need to compare yesterday and the day before yesterday.
$today=date("Y-m-d");
$month_start=date('Y-m-01');
$month_end=date('Y-m-d',strtotime("-1 day"));

$monthb4_start=date('Y-m-01',strtotime("-1 month"));
$monthb4_end=date('Y-m-d',strtotime("-1 month",strtotime($month_end)));

# --- for DEBUG
#echo "INFO : current month period start = ".$month_start."\r\n";
#echo "INFO : current month period end   = ".$month_end."\r\n";
#echo "INFO : prev. month period start   = ".$monthb4_start."\r\n";
#echo "INFO : prev. month period end     = ".$monthb4_end."\r\n";


// associative array to store costs for business applications
$output_array=[];

// connection to the database
$link = mysqli_connect( $db_host, $db_user, $db_pass, $db_name);
if (!$link) {
  $err=mysqli_connect_errno();
  echo "ERROR : unable to connect to db server or db ".$db_name." (".$err.")\r\n";
  return 1;
}


// get splitted costs for the previous period (month to date of current month)
// and store them in the associative array.
$sql="select MeterCategory,truncate(sum(SplittedCost),2) as Cost from ".$db_splittedcosts_table." where Date>='".$monthb4_start."' and Date<='".$monthb4_end."' group by MeterCategory order by MeterCategory; ";
$result = mysqli_query($link, $sql);
if (mysqli_num_rows($result) > 0) { 
  //for each row insert in the associative array the label as the key, and the costs as column[0]
  while($row = mysqli_fetch_assoc($result)) {	  
    $metercat=$row["MeterCategory"];
    $cost=$row["Cost"];

    $output_array[$metercat]=[];
    $output_array[$metercat][0]=$cost;
    $output_array[$metercat][1]=0;
    $output_array[$metercat][2]=0;
    $output_array[$metercat][3]="-100";
    $output_array[$metercat][4]="\u{1F4C9}";
  }
}

//get splitted costs for the current period (month to date of the previous month)
// and store them in the associative array.
$sql="select MeterCategory,truncate(sum(SplittedCost),2) as Cost from ".$db_splittedcosts_table." where Date>='".$month_start."' and Date<='".$month_end."' group by MeterCategory order by MeterCategory; ";
$result = mysqli_query($link, $sql);
if (mysqli_num_rows($result) > 0) {
  //for each row insert in the associative array the label as the key, and the costs as column[1]
  while($row = mysqli_fetch_assoc($result)) {
    $metercat=$row["MeterCategory"];
    $cost=$row["Cost"];

    if (!isset($output_array[$metercat]))
      $output_array[$metercat]=[];

    $output_array[$metercat][1]=$cost;

    //in case we have new metercat yesterday, compared to the day before
    //we need to 0-fill the missing previous values.
    if ( !isset($output_array[$metercat][0]) ){
      $output_array[$metercat][0]=0;
    }


    // let's calculate the DELTA in column[2] and PERCENTAGE in column[3]
    $delta = $output_array[$metercat][1] - $output_array[$metercat][0];
    $output_array[$metercat][2]=$delta;
    if ($output_array[$metercat][1]>0){
      // uncomment the following line for the percentage of yesterday versus the day before
      $output_array[$metercat][3]=round(($output_array[$metercat][0] * 100) / $output_array[$metercat][1],3);
      // uncomment the following line for the percentage
      $output_array[$metercat][3]=round(($delta * 100) / $output_array[$metercat][1],3);

    }else{
      if ($output_array[$metercat][1]>0 )
        $output_array[$metercat][3]="-100";
      else
        $output_array[$metercat][3]="0";
    }

    // let's insert the emoji icon trend
    if ( round($delta,3) > 0 ){
      $output_array[$metercat][4]="\u{1F4C8}";
    }else if ( round($delta,3) == 0 ) {
      $output_array[$metercat][4]="\u{003D}";
    }else{
      $output_array[$metercat][4]="\u{1F4C9}";
    }

  }
}

// print html output
echo "<html><head><title>Metercategory day/day report</title></head><body>\r\n";
echo "<table width='90%' cellspacing='0' cellpadding='0'>\r\n";
echo "<tr><th>MeterCategory</th><th>Prev. month-to-date<br /><small>[".$monthb4_start."&nbsp;/&nbsp;".$monthb4_end."]</small></th><th>Current month-to-date<br /><small>[".$month_start."&nbsp;/&nbsp;".$month_end."]</small></th><th>Delta cost</th><th>Percentage</th><th>Trend</th></tr>\r\n";
foreach ($output_array as $key => $metercat) {
  echo "<tr><td>".$key."</td><td align='right'>".$metercat[0]."</td><td align='right'>".$metercat[1]."</td><td align='right'>".$metercat[2]."</td><td align='center'>".$metercat[3]."%</td><td align='center'>".$metercat[4]."</td></tr>\r\n";
}

echo "</table>";
echo "</body></html>";

mysqli_close($link);
return 0;
?>


