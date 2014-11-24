<div class="page-header">
    <h1><?php echo $page_title ?></h1>
</div>
<?php echo $this->Form->create('Card', array('class' => 'form-horizontal', 'method' => 'post', 'enctype' => 'multipart/form-data')); ?>
<div class="row">
    <div class="col-sm-6">
        <div class="form-group">
            <label for="name" class="col-sm-3">Newsfeed</label>
            <div class="col-sm-9">
                <?php echo $newsfeed['Newsfeeds']['newsfeed_msg']; ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            <label for="name" class="col-sm-3">Created By</label>
            <div class="col-sm-9">
                <?php echo $creatorData['User']['name'] . ' (' . $creatorData['User']['email'] . ')'; ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            <label for="name" class="col-sm-3">Starred</label>
            <div class="col-sm-9">
                <?php echo $newsfeed['Newsfeeds']['stars_count']; ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            <label for="name" class="col-sm-3">Comments</label>
            <div class="col-sm-9">
                <?php echo $newsfeed['Newsfeeds']['comments_count']; ?>
            </div>
        </div>
    </div>
</div>

<div class="page-header">
    <h3>Comments & Starred</h3>
</div>

<div class="table-responsive">
    <table border="0" cellspacing="0" cellpadding="0" class="table-condensed table-bordered table-striped table table-hover">
        <tr>
            <th class="sort-td">Type</th>
            <th class="sort-td">Comment</th>
            <th class="sort-td">Given By</th>
            <th class="sort-td">Created On</th>
        </tr>
        <?php if (count($commentStars) > 0) { ?>
            <?php foreach ($commentStars as $csArr) { ?>
                <tr>
                    <td><?php echo ucfirst($csArr['Commentstar']['type']) ?> </td>
                    <td><?php echo $csArr['Commentstar']['type'] == 'comment' ? $csArr['Commentstar']['comment'] : "-"; ?> </td>
                    <td><?php echo $csArr['Commentstar']['user_name'] ?> </td>
                    <td><?php echo date('Y-m-d h:i:s', $csArr['Commentstar']['created']->sec); ?></td>
                </tr>
            <?php } ?>
        <?php } else { ?>
            <tr>
                <td colspan="4">No comment and starred found.</td>
            </tr>
        <?php } ?>
    </table>
</div>

<div class="page-header">
    <h3>Users who reported Inappropriate</h3>
</div>

<div class="table-responsive">
    <table border="0" cellspacing="0" cellpadding="0" class="table-condensed table-bordered table-striped table table-hover">
        <tr>
            <th class="sort-td">Name</th>
            <th class="sort-td">Phone Number</th>
            <th class="sort-td">Email</th>
        </tr>
        <?php if (count($newsfeed['Newsfeeds']['inappropriatedby_user_list']) > 0) { ?>
            <?php foreach ($newsfeed['Newsfeeds']['inappropriatedby_user_list'] as $iulArr) { ?>
                <?php
                $User = ClassRegistry::init('User');
                $resultUser = $User->find('first', array('conditions' => array('_id' => new MongoId($iulArr['user_id']))));
                ?>
                <tr>
                    <td><?php echo ucfirst($iulArr['user_name']) ?> </td>
                    <td><?php echo $resultUser['User']['phone_no']; ?> </td>
                    <td><?php echo $resultUser['User']['email'] ?> </td>
                </tr>
            <?php } ?>
        <?php } else { ?>
            <tr>
                <td colspan="4">No comment and starred found.</td>
            </tr>
        <?php } ?>
    </table>
</div>
<div class="form-group">
    <div class="col-sm-10">
        <?php
        echo $this->Html->link('Delete', array('action' => 'deletenewsfeed', 'id' => (string) $newsfeed['Newsfeeds']['_id'],
            'full_base' => true), array('class' => 'btn btn-primary'), "Are you sure you wish to delete this newsfeed?");
        ?>
    </div>
</div>
<?php echo $this->form->end(); ?>
