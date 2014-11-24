<?php if(count($staticPageData)>0) { ?>
    <div class="page-header">
        <h1><?php echo $staticPageData['Staticpage']['title'] ?></h1>
    </div>
    <div class="row">
        <div class="col-sm-12">
            <div><?php echo $staticPageData['Staticpage']['description'] ?></div>
        </div>
    </div>
<?php } else { ?>
    <div class="row">
        <div class="col-sm-12">No content available.</div>
    </div>
<?php } ?>