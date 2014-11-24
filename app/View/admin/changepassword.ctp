<div class="page-header">
  <h1><?php echo $page_title ?></h1>
</div>
<?php echo $this->Form->create('Admin', array('class' => 'form-horizontal', 'method'=> 'post')); ?>
    <div class="form-group">
        <label for="oldpassword" class="col-sm-2 control-label">Old Password</label>
        <div class="col-sm-10">
            <input type="password" id="oldpassword" name="data[Admin][oldpassword]" placeholder="Old Password" value="" class="form-control">
        </div>
    </div>
    <div class="form-group">
        <label for="password" class="col-sm-2 control-label">New Password</label>
        <div class="col-sm-10">
            <input type="password" id="password" name="data[Admin][password]" placeholder="New Password" value="" class="form-control">
        </div>
    </div>
    <div class="form-group">
        <label for="password" class="col-sm-2 control-label">Confirm Password</label>
        <div class="col-sm-10">
            <input type="password" id="confirmpassword" name="data[Admin][confirmpassword]" placeholder="Confirm Password" value="" class="form-control">
        </div>
    </div>
    <div class="form-group">
        <div class="col-sm-offset-2 col-sm-10">
            <button type="submit" class="btn btn-info">Change Password</button>
        </div>
    </div>
<?php echo $this->form->end(); ?>



