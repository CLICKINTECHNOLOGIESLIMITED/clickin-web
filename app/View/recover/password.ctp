<?php if (isset($data['error']) && $data['error'] == 0) { ?>
    <div class="page-header">
        <h1><?php echo $page_title ?></h1>
    </div>
    <?php echo $this->Form->create('User', array('class' => 'form-horizontal', 'method' => 'post')); ?>
    <div class="form-group">
        <label for="password" class="col-sm-2 control-label">New Password</label>
        <div class="col-sm-10">
            <input type="password" id="password" name="data[User][password]" placeholder="New Password" 
                   value="<?php echo (isset($data['password'])) ? $data['password'] : ""; ?>" class="form-control">
        </div>
    </div>
    <div class="form-group">
        <label for="password" class="col-sm-2 control-label">Confirm Password</label>
        <div class="col-sm-10">
            <input type="password" id="confirmpassword" name="data[User][confirmpassword]" placeholder="Confirm Password" 
                   value="<?php echo (isset($data['password'])) ? $data['confirmpassword'] : ""; ?>" class="form-control">
        </div>
    </div>
    <div class="form-group">
        <div class="col-sm-offset-2 col-sm-10">
            <button type="submit" class="btn btn-info">Save</button>
        </div>
    </div>
    <?php echo $this->form->end(); ?>
<?php } ?>