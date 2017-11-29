<?php
/*  ASTROLOGY API
*   2017 Peter Olejniczak
*   This is an input/output parser for the Swiss Ephemeris CLI application (swetest)
*   It will accept POST or GET requests, check them for sanity, and then query swetest
*   Returned results are in JSON, with the addition of Zodiac longitudes, and Aspect calculations
*   TODO: Intercept calculations
*/

$SCRIPTMON['start-time'] = microtime(true);

header("Access-Control-Allow-Origin: *");
error_reporting(0);
////STAGE 0
//Create some helpful functions and set some variables

//Sign short names
$signShort = array(
        0 => 'Ari',
        'Tau',
        'Gem',
        'Can',
        'Leo',
        'Vir',
        'Lib',
        'Sco',
        'Sag',
        'Cap',
        'Aqu',
        'Pis'
    );
//Sign long names
$signLong = array(
    0 => 'Aries',
    'Taurus',
    'Gemini',
    'Cancer',
    'Leo',
    'Virgo',
    'Libra',
    'Scorpio',
    'Sagittarius',
    'Capricorn',
    'Aquarius',
    'Pisces'
);
//Exaltations
$exaltations = array(
    "Sun" => "19 Ari",
    "Moon" => "3 Tau",
    "Mercury" => "15 Vir",
    "Venus" => "27 Pis",
    "Mars" => "28 Cap",
    "Jupiter" => "15 Can",
    "Saturn" => "21 Lib",
    "North Node" => "3 Gem"
);
$renameElements = array(
    "mean Apogee" => "BlackMoonLilith",
    "true Node" => "AscendingNode",
    "MC" => "Midheaven",
);

//Give two possible values. Return first one that exists.
//*Future: array input to allow for arbitrary number of values
function fallBackLoad($firstPick, $secondPick)
{
    if ($firstPick) {
        return $firstPick;
    } else {
        return $secondPick;
    }
}

//Convert longitude into Zodiac formatted longitude
Function Convert_Longitude($longitude)
{
    global $signShort;
    $sign_num    = floor($longitude / 30);
    $pos_in_sign = $longitude - ($sign_num * 30);
    $deg         = floor($pos_in_sign);
    $full_min    = ($pos_in_sign - $deg) * 60;
    $min         = floor($full_min);
    $full_sec    = round(($full_min - $min) * 60);
    
    if ($deg < 10) {
        $deg = "0" . $deg;
    }
    
    if ($min < 10) {
        $min = "0" . $min;
    }
    
    if ($full_sec < 10) {
        $full_sec = "0" . $full_sec;
    }
    
    return $deg . " " . $signShort[$sign_num] . " " . $min . "' " . $full_sec . chr(34);
}

//Check if two numbers are within a certain value of each other
function withinRange($num1, $num2, $allowedDifference)
{
    if (abs($num1 - $num2) <= $allowedDifference) {
        return true;
    }
    return false;
}

//Take two degrees and calculate the astrological aspect between them
function getAspect($degree1, $degree2)
{
    $aspects = array(
        "Conjunction" => array(
            "degree" => 0,
            "orb" => 6
        ),
        "Opposition" => array(
            "degree" => 180,
            "orb" => 6
        ),
        "Trine" => array(
            "degree" => 120,
            "orb" => 6
        ),
        "Square" => array(
            "degree" => 90,
            "orb" => 6
        ),
        "Sextile" => array(
            "degree" => 60,
            "orb" => 6
        )/*,
        "Quintile" => array(
            "degree" => 72,
            "orb" => 3
        ),
        "Inconjunct" => array(
            "degree" => 150,
            "orb" => 3
        ),
        "Semi-Sextile" => array(
            "degree" => 30,
            "orb" => 2
        ),
        "Semi-Square" => array(
            "degree" => 45,
            "orb" => 2
        ),
        "Sesquiquadrate" => array(
            "degree" => 135,
            "orb" => 2
        )*/
    );
    while ($degree1 >= 360) {
        $degree1 -= 360;
    }
    while ($degree2 >= 360) {
        $degree2 -= 360;
    }
    $diff = $degree1 - $degree2;
    foreach ($aspects as $key => $aspect) {
        if (withinRange(abs($diff), $aspect['degree'], $aspect['orb'])) {
            return array(
                "type" => $key,
                "angle" => $diff
            );
        }
    }
    return false;
}

function runCalcs($latitude, $longitude, $year, $month, $day, $hour, $minute, $timezone) {
    global $fuckinhell, $renameElements;
    try {
        $unixdate = new DateTime($timezone);
        $unixdate->setDate($year, $month, $day);
        if(!$minute) {$minute = "0";}
        $unixdate->setTime($hour, $minute);
        $unixdate->setTimezone(new DateTimeZone('UTC'));
    }
    catch (Exception $e) {
        $fuckinhell['timezone'] = $e->getMessage();
    }

    $datestring = $unixdate->format('d.m.Y');
    $timestring = $unixdate->format('H:i:s');
    //Round them lat/longs
    $latitude   = round($latitude, 4);
    $longitude  = round($longitude, 4);

    $execstring = "../shell/swetest.sh -b" . $datestring . " -ut" . $timestring . " -p0123456789ADFGHIt -eswe -house" . $longitude . "," . $latitude . ",p -fPlsj -g, -head";
    exec($execstring, $return);

    foreach ($return as $rawline) {
        global $signLong;
        $translated = NULL; //clear out working data array
        $rawdata    = explode(",", $rawline); //explode our shit
        foreach ($rawdata as $key => $value) {
            $rawdata[$key] = trim($value); //iterate and trim all values
        }
        $translated['longitude'] = $rawdata[1]; //set longitude
        //$translated['zodiac']    = Convert_Longitude($rawdata[1]); //convert longitude into zodiac longitude
        $translated['speed']     = $rawdata[2]; //get our objects speed
        $translated['sign'] = $signLong[floor($rawdata[1] / 30)];
        if (array_key_exists(3, $rawdata)) {
            $translated['house'] = $rawdata[3]; //if it's there add the house
        }
        
        if ($renameElements[$rawdata[0]]) { $rawdata[0] = $renameElements[$rawdata[0]];}

        if (strpos($rawdata[0], 'house') !== false) {
            $compiled['Houses'][trim(substr($rawdata[0], -2))] = $translated; //add house to Houses
            //Check which sign this house' cusp is in and increment by one.
            $monitor['HouseZodiacs'][floor($translated['longitude'] / 30)]++;
        } else {
            //It's not a house. Check it's angle against other objects, then add to Objects
            foreach ($compiled['Objects'] as $named => $planet) { //iterate every other object
                $aspect = getAspect($rawdata[1], $planet['longitude']); //check if there's aspects between these two
                if ($aspect) {
                    $compiled['Aspects'][$named][$rawdata[0]] = $aspect; //add relationship
                    //$compiled['Aspects'][$rawdata[0]][$named] = $aspect; //bandwidth is cheap; go both ways
                }
            }
            $compiled['Objects'][$rawdata[0]] = $translated; //add object to Objects
        }
    }

    ////STAGE 4
    //LOOK FOR INTERCEPTED SIGNS
    for ($iLIntercepts = 0; $iLIntercepts < 12; $iLIntercepts++) {
        if ($monitor['HouseZodiacs'][$iLIntercepts] == 0) {
            $compiled['Intercepts']['Skipped'][] = $signLong[$iLIntercepts];
        }
        if ($monitor['HouseZodiacs'][$iLIntercepts] > 1) {
            $compiled['Intercepts']['Duplicated'][] = $signLong[$iLIntercepts];
        }
    }
    return $compiled;
}

////STAGE 0
//Prepare Query Database
$databaseQuery = new SQLite3('.requests');
$databaseQuery->query('CREATE TABLE IF NOT EXISTS Queries (hash text PRIMARY KEY, request text) WITHOUT ROWID');


////STAGE 1
//GRAB OUR POST/GET VARIABLES

$hashRequest = fallBackLoad($_POST['hashRequest'], $_GET['hashRequest']);

if ($hashRequest) {
    $results = $databaseQuery->query('SELECT * FROM Queries WHERE hash="' . $hashRequest . '";');
    while ($row = $results->fetchArray()) {
        print_r($row);
    }
} else {
    $latitude  = fallBackLoad($_POST['latitude'], $_GET['latitude']);
    $longitude = fallBackLoad($_POST['longitude'], $_GET['longitude']);
    $year      = fallBackLoad($_POST['year'], $_GET['year']);
    $month     = fallBackLoad($_POST['month'], $_GET['month']);
    $day       = fallBackLoad($_POST['day'], $_GET['day']);
    $hour      = fallBackLoad($_POST['hour'], $_GET['hour']);
    $minute    = fallBackLoad($_POST['minute'], $_GET['minute']);
    $timezone  = fallBackLoad($_POST['timezone'], $_GET['timezone']);
    $userID    = fallBackLoad($_POST['ID'], $_GET['ID']);
}

//CHECK THAT OUR INPUTS ARE SANE
//* Try to exit gracefully and provide helpful error messages on failures
//* Future: collapse into functions so we can escape this mess of if/else's

if (!$latitude) {
    $fuckinhell['latitude'] = "not present";
} else {
    if (!is_numeric($latitude)) {
        $fuckinhell['latitude'] = "not a number";
    }
}

if (!$longitude) {
    $fuckinhell['longitude'] = "not present";
} else {
    if (!is_numeric($longitude)) {
        $fuckinhell['longitude'] = "not a number";
    }
}

if (!$year) {
    $fuckinhell['year'] = "not present";
} else {
    if (!is_numeric($year)) {
        $fuckinhell['year'] = "not a number";
    }
}

if (!$month) {
    $fuckinhell['month'] = "not present";
} else {
    if (!is_numeric($month)) {
        $fuckinhell['month'] = "not a number";
    } else {
        if ($month < 1) {
            $fuckinhell['month'] = "value too low";
        } else {
            if ($month > 12) {
                $fuckinhell['month'] = "value too high";
            }
        }
    }
}

if (!$day) {
    $fuckinhell['day'] = "not present";
} else {
    if (!is_numeric($day)) {
        $fuckinhell['day'] = "not a number";
    } else {
        if ($day < 1) {
            $fuckinhell['day'] = "value too low";
        } else {
            if ($day > 31) {
                $fuckinhell['day'] = "value too high";
            }
        }
    }
}

if (!$hour) {
    $fuckinhell['hour'] = "not present";
} else {
    if (!is_numeric($hour)) {
        $fuckinhell['hour'] = "not a number";
    } else {
        if ($hour < 0) {
            $fuckinhell['hour'] = "value too low";
        } else {
            if ($hour > 23) {
                $fuckinhell['hour'] = "value too high";
            }
        }
    }
}

if (!$minute) {
    //$fuckinhell['minute'] = "not present";
    $minute = "0";
} else {
    if (!is_numeric($minute)) {
        $fuckinhell['minute'] = "not a number";
    } else {
        if ($minute < 0) {
            $fuckinhell['minute'] = "value too low";
        } else {
            if ($minute > 59) {
                $fuckinhell['minute'] = "value too high";
            }
        }
    }
}

$return = runCalcs($latitude, $longitude, $year, $month, $day, $hour, $minute, $timezone);

//create intial return array with request data
$seed = array(
    "Request" => array(
        "date" => $datestring,
        "time" => $timestring,
        "timezone" => 'UTC',
        "latitude" => $latitude,
        "longitude" => $longitude
    ),
    "Objects" => array(),
    "Aspects" => array(),
    "Houses" => array()
);
$compiled = array_merge($seed, $return);

$monitor = array(
    "HouseZodiacs" => array(0,0,0,0,0,0,0,0,0,0,0,0)
);
$jsoncompile = json_encode($compiled);
$hashRequest = hash('sha512', $jsoncompile, false);
$filenameTarget = '.request/' . $hashRequest;
    ////STAGE 3
    //PARSE SOME NUMBERS

    //now iterate every line of data and integrate with return array
    

////STAGE LAST
//WE DONE BITCHES LOG AND SEND THAT SHIT OOT
if (!file_exists($filenameTarget)) {file_put_contents($filenameTarget, $jsoncompile);}
$compiled['Request']['Status'] = "Success";
$compiled['Request']['ID'] = $hashRequest;
$compiled['Request']['ExecTime'] = (microtime(true) - $SCRIPTMON['start-time']) . " milliseconds";
//file_put_contents(".log/" . escapeshellcmd($userID), date("c") . ":" . $_SERVER['REMOTE_ADDR'] . ":" . json_encode($compiled['Request']) . "\n", FILE_APPEND);
$jsoncompile                     = json_encode($compiled);
echo $jsoncompile;


?>
