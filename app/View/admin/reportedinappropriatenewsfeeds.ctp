<div class="page-header">
    <h1><?php echo $page_title ?></h1>
</div>
<div class="table-responsive">
    <table border="0" cellspacing="0" cellpadding="0" class="table-condensed table-bordered table-striped table table-hover">
        <tr>
            <th class="sort-td">Newsfeed Id</th>
            <th class="sort-td"><?php echo $this->Paginator->sort('newsfeed_msg', 'Newsfeed Message'); ?></th>
            <th class="sort-td">Users who Reported</th>
            <th class="sort-td">Creator</th>
            <th class="sort-td">Other Details</th>
            <th class="sort-td" style="width:9%;">Action</th>
        </tr>
        <?php if (count($newsfeeds) > 0) { ?>
            <?php foreach ($newsfeeds as $nfArr) { ?>
                <tr>
                    <td><?php echo $nfArr['Newsfeeds']['_id'] ?> </td>
                    <td><?php echo $nfArr['Newsfeeds']['newsfeed_msg'] ?> </td>
                    <td>
                        <?php 
                        $reportedByUserStr = ""; 
                        if(isset($nfArr['Newsfeeds']['inappropriatedby_user_list'])) {
                            foreach($nfArr['Newsfeeds']['inappropriatedby_user_list'] as $rbuArr) { 
                                $reportedByUserStr .= $rbuArr['user_name'].', ';
                            } 
                        }
                        echo rtrim($reportedByUserStr, ', ');
                        ?>
                    </td>
                    <td>
                        <?php 
                            $User = ClassRegistry::init('User');
                            $params = array(
                                'fields' => array( '_id', 'name' ),
                                'conditions' => array('_id' => $nfArr['Newsfeeds']['user_id']),
                            );
                            $results = $User->find('first', $params);
                            echo $results['User']['name'];
                        ?>
                    </td>
                    <td>
                        <b>Comments :</b> <?php echo (isset($nfArr['Newsfeeds']['comments_count'])) ? $nfArr['Newsfeeds']['comments_count'] : 0; ?><br>
                        <b>Starred :</b> <?php echo (isset($nfArr['Newsfeeds']['stars_count'])) ? $nfArr['Newsfeeds']['stars_count'] : 0; ?>
                    </td>
                    <td>
                        <?php echo $this->Html->link('Delete',array('action' => 'deletenewsfeed','id' => (string) $nfArr['Newsfeeds']['_id'], 
                            'full_base' => true),array(),"Are you sure you wish to delete this newsfeed?"); ?>
                        <?php echo $this->Html->link('View',array('action' => 'viewnewsfeed','id' => (string) $nfArr['Newsfeeds']['_id'], 
                            'full_base' => true)); ?>
                    </td>
                </tr>
            <?php } ?>    
            <tr>
                <td colspan="6">
                    <ul class="pagination">
                        <?php 
                        echo $this->Paginator->prev('<<', array('class' => '', 'tag' => 'li', 'disabledTag' => 'a'), null, array('class' => 'disabled', 'tag' => 'li'));
                        echo $this->Paginator->numbers(array('tag' => 'li', 'separator' => '', 'currentClass' => 'active', 'currentTag' => 'a'));
                        echo $this->Paginator->next('>>', array('class' => '', 'tag' => 'li'), null, array('class' => '', 'tag' => 'li'));
                        ?>
                    </ul>
                </td>
            </tr>
        <?php } else { ?>
            <tr>
                <td colspan="6">No record found.</td>
            </tr>    
        <?php } ?>
    </table>
</div>