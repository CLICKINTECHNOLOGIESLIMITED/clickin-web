<?php /* if ($chatDetailArr['Chat']['type'] == 6) { ?>
  <script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
  <script type="text/javascript">
  var map;
  var geocoder;
  var centerChangedLast;
  var reverseGeocodedLast;
  var currentReverseGeocodeResponse;
  function initialize() {
  var latlng = new google.maps.LatLng(<?php echo $chatDetailArr['Chat']['location_coordinates'] ?>);
  var myOptions = {
  zoom: 15,
  center: latlng,
  mapTypeId: google.maps.MapTypeId.ROADMAP
  };
  map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
  geocoder = new google.maps.Geocoder();

  var image = '<?php echo $this->webroot; ?>img/bullet-blue.png';
  var marker = new google.maps.Marker({
  position: latlng,
  map: map,
  icon: image
  });
  // To add the marker to the map, call setMap();
  marker.setMap(map);

  var populationOptions = {
  strokeColor: '#53c7db',
  strokeOpacity: 0.8,
  strokeWeight: 2,
  fillColor: '#9ed4de',
  fillOpacity: 0.35,
  map: map,
  center: latlng,
  radius: 200
  };
  // Add the circle for this city to the map.
  cityCircle = new google.maps.Circle(populationOptions);
  }
  google.maps.event.addDomListener(window, 'load', initialize);
  </script>
  <?php } */ ?>

<?php /*
  $main_wrapper = '';
  $head = '';
  $thumimg = '';
  $hbox = '';
  $headings = '';
  $ffimg = array();
  $subheading = '';
  $timebox = '';
  $section = '';
  $fcon1 = '';
  $nbox = '';
  $iicon = array();
  $fr = '';
  $hr = '';
  if ($chatDetailArr['Chat']['type'] == 3) {
  $main_wrapper = 'style="max-width: 300px !important;"';
  $head = 'style="padding: 5px 10px 0;"';
  $thumimg = 'style="width: 50px;"';
  $headings = 'style="margin-top: 0px; line-height: 20px; font-size: 15px;"';
  $ffimg = array('style' => 'width: 16px;');
  $subheading = 'style="line-height: 20px; font-size: 14px; margin-top: 0px;"';
  $timebox = 'style="font-size: 13px;line-height: 20px;margin-top: 6px;width: 47px !important;"';
  $section = 'style="padding: 0 10px 3px;"';
  $fcon1 = 'style="padding: 5px;"';
  $nbox = 'style="font-size: 22px;"';
  $iicon = array('style' => 'width:28px;');
  } elseif ($chatDetailArr['Chat']['type'] == 4) {
  $main_wrapper = 'style="max-width: 208px !important;"';
  $head = 'style="padding: 15px 10px 0;"';
  $thumimg = 'style="width: 40px;"';
  $hbox = 'style="padding: 3px; width: 55%;"';
  $headings = 'style="margin-top: 0px; line-height: 20px; font-size: 14px;"';
  $ffimg = array('style' => 'width: 15px;');
  $subheading = 'style="line-height: 20px; font-size: 14px; margin-top: 0px;"';
  $timebox = 'style="font-size: 11px;line-height: 20px; margin-top: 6px; width: 42px !important;"';
  $section = 'style="padding: 0px 10px;"';
  $fcon1 = 'style="padding: 5px;"';
  $nbox = 'style="font-size: 14px;margin-top: 0px;margin-right: 2px;"';
  $iicon = array('style' => 'width:22px;');
  $fr = 'style="padding: 0 10px 5px;"';
  $hr = 'style="margin: 6px 0;"';
  }
  ?>


  <div class="main-wrapper" <?php echo $main_wrapper; ?>>
  <header <?php echo $head; ?>>
  <div class="thumimg" <?php echo $thumimg; ?>>
  <a href="#"><img src="<?php echo $senderDetail['user_pic']; ?>"/></a>
  </div>
  <div class="headingBox" <?php echo $hbox; ?>>
  <span class="headings" <?php echo $headings; ?>><?php echo $senderDetail['name']; ?></span>
  <span class="forwardarrow">
  <a href="#"><?php echo $this->Html->image('forward_icon.jpg', $ffimg); ?></a>
  </span>
  <span class="subheading" <?php echo $subheading; ?>><?php echo $receiverUserDetail['name']; ?></span>
  </div>
  <div class="timeBox" <?php echo $timebox; ?>><?php echo gmdate('h:i A', $sharingDetailArr['Sharing']['created']->sec); ?></div>
  <hr <?php echo $hr ?> />
  </header>

  <?php if ($chatDetailArr['Chat']['type'] >= 2 && $chatDetailArr['Chat']['type'] < 5 || $chatDetailArr['Chat']['type'] == 6) { ?>
  <section <?php echo $section ?>>
  <div class="videoContainer">
  <?php if ($chatDetailArr['Chat']['type'] == 2) { ?>
  <a href="#"><img src="<?php echo $chatDetailArr['Chat']['content']; ?>" /></a>
  <?php } elseif ($chatDetailArr['Chat']['type'] == 3) { ?>
  <a href="#"><?php echo $this->Html->image('volumeimg.jpg'); ?></a>
  <?php } elseif ($chatDetailArr['Chat']['type'] == 4) { ?>
  <a href="#"><img src="<?php echo $chatDetailArr['Chat']['video_thumb']; ?>" /></a>
  <?php } elseif ($chatDetailArr['Chat']['type'] == 6) { ?>
  <a href="#"><img src="<?php echo $chatDetailArr['Chat']['content']; ?>" /></a>
  <?php /* <div id="map" style="width:500px; height:800px">
  <div id="map_canvas" style="width:100%; height:800px"></div>
  </div> */ /* ?>
  <?php } ?>
  </div>
  </section>
  <?php } ?>

  <footer <?php echo $fr ?>>
  <?php if ($chatDetailArr['Chat']['type'] != 5 && $chatDetailArr['Chat']['type'] != 6) { ?>
  <?php if ($chatDetailArr['Chat']['clicks'] == NULL) { ?>
  <div class="footercontainer">&nbsp;</div>
  <?php } else { ?>
  <div class="footercontainer1 clearfix" <?php echo $fcon1; ?>>
  <span class="numberBox" <?php echo $nbox; ?>><?php echo $chatDetailArr['Chat']['clicks']; ?> </span>
  <span class="imgicon">
  <?php echo $this->Html->image('screen1icon.png', $iicon); ?>
  </span>
  <span class="numberBox" style="float: none;" <?php echo $nbox; ?>> <?php /* echo (strlen($chatDetailArr['Chat']['message']) <= 25 ? $chatDetailArr['Chat']['message'] :
  substr($chatDetailArr['Chat']['message'], 0, 25) . '...'); */ /* echo $chatDetailArr['Chat']['message']; ?></span>
  </div>
  <?php } ?>
  <?php } else { ?>
  <?php if ($chatDetailArr['Chat']['type'] == 6) { ?>
  <div class="footercontainer clearfix">
  <div class="contentBox">Location Shared</div>
  </div>
  <?php } elseif ($chatDetailArr['Chat']['type'] == 5) { ?>
  <div class="footercontainer clearfix">
  <div class="container">
  <?php if ($chatDetailArr['Chat']['cards'][8] != '') { ?>
  <div class="bearhug">
  <span class="textBox"><?php echo $chatDetailArr['Chat']['cards'][4]; ?></span>
  <img src="<?php echo $chatDetailArr['Chat']['cards'][3]; ?>" style="width:194px;height:254px;"/>
  <span class="textBox1"><?php echo $chatDetailArr['Chat']['cards'][4]; ?></span>
  </div>
  <?php } else { ?>
  <div class="clickBoxx">
  <div class="clickbgg">
  <span class="textBoxnew"><?php echo $chatDetailArr['Chat']['cards'][4]; ?></span>
  <?php echo $this->Html->image('clickbgg.png', array('style' => 'width:194px;height:254px;')); ?>
  <span class="textBoxnew1"><?php echo $chatDetailArr['Chat']['cards'][4]; ?></span>
  <div class="container">
  <table style="width:100%;height: 125px;font-size: 25px;font-weight: bold;">
  <tr>
  <td valign="middle" align="center" style="color: #FFF;"><?php echo $chatDetailArr['Chat']['cards'][1]; ?></td>
  </tr>
  </table>
  </div>
  </div>
  </div>
  <?php } ?>
  <div class="accepted">
  <h2>ACCEPTED !</h2>
  <hr />
  <span class="acceptedimg"><?php echo $this->Html->image('accepted.jpg'); ?></span>
  </div>
  </div>
  </div>
  <?php } ?>
  <?php } ?>
  <hr <?php echo $hr; ?> />
  </footer>
  </div>

  <?php /*
  echo '<br style="clear:both;"><pre>';
  print_r($chatDetailArr);
  ?>
  <?php print_r($senderDetail); ?>
  <?php
  print_r($receiverUserDetail);
  echo '</pre>'; */
?>

<link href='http://fonts.googleapis.com/css?family=Open+Sans:400,800,700,600' rel='stylesheet' type='text/css'>
<style>
    body{
        font-size: 18px;
        font-family: 'Open Sans', sans-serif;
        font-weight: 600;
        line-height: normal;
        padding:0;
        margin: 0;
        height: 100%;
    }
    h1{
        font-weight: 800;
        line-height: normal;
        margin: 0;
        padding: 0;
        color: #fff;
        font-size: 8em;
        text-align: center;
        line-height: 135px;
    }
    p{
        font-size: 2em;
        font-weight: 600;
        margin: 0;
        padding: 0;
        color: #fff;
        text-align: center;
    }

    .container {
        position: absolute;
        top: 75px;
        display:table;
        height:190px;
    }

    .content-box{
        display: table-cell !important;
        vertical-align: middle;
        padding: 0 38px 0 41px;
        color:#fff;
        text-align: center;
        font-weight: 700;
        font-size: 30px;
        line-height: 33px;
        width: 260px;
    }

</style>

<?php
// http://localhost/clickinweb/pages/getscreenshot/chatid:53726076252e08f7358b4567
// http://localhost/clickinweb/pages/getscreenshot/chatid:537324bd252e0866308b4567
// image
// http://localhost/clickinweb/pages/getscreenshot/chatid:5372eba6252e08712a8b4567
// http://localhost/clickinweb/pages/getscreenshot/chatid:5374b3d7252e0819358b4567  
// 
// screenshots are not required for audio and video
// 
// http://localhost/clickinweb/pages/getscreenshot/chatid:53800d2d252e08a67a8b4567
// 
//  location
//  http://localhost/clickinweb/pages/getscreenshot/chatid:5385e815252e08ac758b4567 
// c card
// http://saurabh-singh/clickinweb/pages/getscreenshot/chatid:5373509c252e08f0778b4567
// t card
// http://saurabh-singh/clickinweb/pages/getscreenshot/chatid:5371fbf9252e084f678b4567
?>
<?php if ($chatDetailArr['Chat']['type'] == 1) { //chat ?>

    <?php if (trim($chatDetailArr['Chat']['message']) != '') { ?>
        <div style="width:100%; height: 100%; max-width:650px; margin: auto;float: left;">
            <div style="float: left; display: block; width: 100%; background:#374667; height: 600px; position: relative;">
                <div style="padding: 0 55px;display: block; vertical-align: middle; position: relative; top:50%; margin-top: -143px;height: 285px;">
                    <h1><?php echo trim($chatDetailArr['Chat']['clicks']) . 'C'; ?></h1>
                    <div style="margin-top: 50px;">
                        <p><?php echo trim($chatDetailArr['Chat']['message']); ?></p>
                    </div>
                </div>
                <div style="width:100%; position: absolute; bottom:20px; text-align: center; display: block;">
                    <?php echo $this->Html->image('Clickin-mini-logo.png', array('width' => "80")); ?>
                </div>
            </div>
        </div>
    <?php } else { ?>
        <div style="width:100%; height: 100%; max-width:650px; margin: auto;float: left;">
            <div style="float: left; display: block; width: 100%; background:#374667; height: 600px; position: relative;">
                <div style="padding: 0 55px;display: block; vertical-align: middle; position: relative; top:50%; margin-top: -143px;height: 285px;">
                    <h1><?php echo trim($chatDetailArr['Chat']['clicks']) . 'C'; ?></h1>
                </div>
                <div style="width:100%; position: absolute; bottom:20px; text-align: center; display: block;">
                    <?php echo $this->Html->image('Clickin-mini-logo.png', array('width' => "80")); ?>
                </div>
            </div>
        </div>
    <?php } ?>
<?php } elseif ($chatDetailArr['Chat']['type'] == 2) { //image ?>
    <?php if (trim($chatDetailArr['Chat']['message']) != '') { ?>
        <div style="width:100%; height: 100%;  margin: auto;float: left;">
            <div style="clear:both; display: block;">
                <div style="width:100%; position: relative; text-align: left; display: block;">
                    <img src="<?php echo $chatDetailArr['Chat']['content']; ?>"  />
                    <?php
                    list($width, $height) = getimagesize($chatDetailArr['Chat']['content']);
                    ?>
                    <div style="width: <?php echo $width; ?>px;">
                        <div style="position: absolute; bottom:90px;display: block; margin-left: <?php echo ($width / 2) - 60; ?>px;">
                            <div style="width:60px; height:60px; display:inline-block;background: url('<?php echo $this->webroot; ?>img/Clickin-mini-logo.png') no-repeat top center;background-size: 100%; position: absolute;bottom:0;left:0;z-index: 2;" ></div>
                            <div style="width:60px; height:60px; display:inline-block;background: url('<?php echo $this->webroot; ?>img/Clickin-point-bg.png') no-repeat top center;background-size: 100%;position: absolute;left:48px;bottom:0;z-index: 1;" >
                                <span style="display: block;font-weight: 700;text-align: center;color:#fff;padding:21px 10px 0 8px;font-size: 13px; "><?php echo trim($chatDetailArr['Chat']['clicks']) . 'C'; ?></span>
                            </div>
                        </div>
                    </div>
                    <div style="display:block;position:relative; bottom:0; left:0;text-align: center; background:#374667;color:#fff;margin-top: -6px;width:<?php echo $width; ?>px;">
                        <div style="text-align: left;font-size:1.3em;padding:20px 40px; text-align: left; word-break: break-all;">
                            <?php echo trim($chatDetailArr['Chat']['message']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php } else { ?>
        <div style="width:100%; height: 100%;  margin: auto;float: left;">
            <div style="clear:both; display: block;">
                <div style="width:100%; position: relative; text-align: left; display: block;">
                    <img src="<?php echo $chatDetailArr['Chat']['content']; ?>"  />
                    <?php
                    list($width, $height) = getimagesize($chatDetailArr['Chat']['content']);
                    ?>
                    <div style="width: <?php echo $width; ?>px;">
                        <div style="position: absolute; bottom:90px;display: block; margin-left: <?php echo ($width / 2) - 60; ?>px;">
                            <div style="width:60px; height:60px; display:inline-block;background: url('<?php echo $this->webroot; ?>img/Clickin-mini-logo.png') no-repeat top center;background-size: 100%; position: absolute;bottom:0;left:0;z-index: 2;" ></div>
                            <div style="width:60px; height:60px; display:inline-block;background: url('<?php echo $this->webroot; ?>img/Clickin-point-bg.png') no-repeat top center;background-size: 100%;position: absolute;left:48px;bottom:0;z-index: 1;" >
                                <span style="display: block;font-weight: 700;text-align: center;color:#fff;padding:21px 10px 0 8px;font-size: 13px; "><?php echo trim($chatDetailArr['Chat']['clicks']) . 'C'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
<?php } elseif ($chatDetailArr['Chat']['type'] == 5) { //trade card ?>

    <?php if ($chatDetailArr['Chat']['cards'][8] != '') { ?>
        <div style="position:relative; display: inline-block;">
            <span style="position: absolute;top: 0px;color: #fff;left: 12px;font-weight: 700;font-size: 40px;"><?php echo $chatDetailArr['Chat']['cards'][4]; ?></span>
            <img src="<?php echo $chatDetailArr['Chat']['cards'][3]; ?>"/>
            <span style="position: absolute;bottom:9px;color: #fff;right: 43px;font-weight: 700;font-size: 40px;"><?php echo $chatDetailArr['Chat']['cards'][4]; ?></span>
        </div>
    <?php } else { ?>
        <div style="position:relative; display: inline-block; width:259px;">
            <span style="position: absolute;top: 0px;color: #fff;left: 7px;font-weight: 700;font-size: 40px;"><?php echo $chatDetailArr['Chat']['cards'][4]; ?></span>
            <?php echo $this->Html->image('clickbgg.png', array('style' => '')); ?>
            <span style="position: absolute;bottom:7px;color: #fff;right: 45px;font-weight: 700;font-size: 40px;"><?php echo $chatDetailArr['Chat']['cards'][4]; ?></span>
            <div class="container">
                <div class="content-box">
                    <?php echo trim($chatDetailArr['Chat']['cards'][1]); ?>
                </div>
            </div>
        </div>        
    <?php } ?>

<?php } elseif ($chatDetailArr['Chat']['type'] == 6) { ?>

    <div style="width:100%; height: 100%;  margin: auto;float: left;">
        <div style="clear:both; display: block;">
            <div style="width:100%; position: relative; text-align: left; display: block;">
                <img src="<?php echo $chatDetailArr['Chat']['content']; ?>"  />
                <?php
                list($width, $height) = getimagesize($chatDetailArr['Chat']['content']);
                ?>
                <div style="width: <?php echo $width; ?>px;">
                    <div style="position: absolute; bottom:90px; ;display: block; margin-left: <?php echo ($width / 2) - 40; ?>px;">
                        <div style="width:80px; height:80px; display:inline-block;background: url('<?php echo $this->webroot; ?>img/Clickin-mini-logo.png') no-repeat top center;background-size: 100%; position: absolute;bottom:0;left:0;z-index: 2;" ></div>
                    </div>
                </div>
                <div style="display:block;position:relative; bottom:0; left:0;text-align: center; background:#374667;color:#fff;margin-top: -6px;width:<?php echo $width; ?>px;">
                    <div style="text-align: left;font-size:1.3em;padding:20px 40px; text-align: left; word-break: break-all;">
                        <?php echo trim($chatDetailArr['Chat']['message']); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>    
<?php } ?>

