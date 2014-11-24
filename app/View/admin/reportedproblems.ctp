<div class="page-header">
    <h1><?php echo $page_title ?></h1>
</div>
<div class="table-responsive">
    <table border="0" cellspacing="0" cellpadding="0" class="table-condensed table-bordered table-striped table table-hover">
        <tr>
            <th class="sort-td">Id</th>
            <th class="sort-td"><?php echo $this->Paginator->sort('problem_type', 'Problem Type'); ?></th>
            <th class="sort-td">User who Reported</th>
            <th class="sort-td">Comment</th>
            <th class="sort-td">Spam/Abuse Type</th>
            <th class="sort-td">Reported Date</th>
            <th class="sort-td" style="width:9%;">Action</th>
        </tr>
        <?php if (count($feedbacks) > 0) { ?>
            <?php $rowCount = ($this->Paginator->current()>1) ? $this->Paginator->param('limit') * ($this->Paginator->current()-1)+1 : 1; ?>
            <?php foreach ($feedbacks as $fdArr) { ?>
                <tr>
                    <td><?php echo $rowCount; ?> </td>
                    <td><?php echo $fdArr['Feedback']['problem_type']; ?></td>
                    <td>
                        <?php 
                            $User = ClassRegistry::init('User');
                            $params = array(
                                'fields' => array( '_id', 'name' ),
                                'conditions' => array('_id' => $fdArr['Feedback']['user_id']),
                            );
                            $results = $User->find('first', $params);
                            echo $results['User']['name'];
                        ?> 
                    </td>
                    <td><?php echo $fdArr['Feedback']['comment'] ?> </td>
                    <td><?php echo isset($fdArr['Feedback']['spam_or_abuse_type']) ? $fdArr['Feedback']['spam_or_abuse_type'] : '-'; ?></td>
                    <td><?php echo date('Y-m-d h:i:s', $fdArr['Feedback']['created']->sec); ?></td>
                    <td>
                        <?php echo $this->Html->link('To Do Action',array('action' => 'sendmessage','id' => (string) $fdArr['Feedback']['_id'], 
                            'full_base' => true)); ?>
                    </td>
                </tr>  
                <?php $rowCount++; ?>
            <?php } ?>
            <tr>
                <td colspan="7">
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