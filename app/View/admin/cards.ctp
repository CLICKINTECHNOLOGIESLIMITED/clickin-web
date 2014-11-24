<div class="page-header">
    <h1><?php echo $page_title ?></h1>
</div>
<?php echo $this->Html->link('Add Card',array('action' => 'addcard','full_base' => true)); ?>
<div class="table-responsive">
    <table border="0" cellspacing="0" cellpadding="0" class="table-condensed table-bordered table-striped table table-hover">
        <tr>
            <th class="sort-td">Image</th>
            <th class="sort-td"><?php echo $this->Paginator->sort('title', 'Name'); ?></th>
            <th class="sort-td">Category</th>
            <th class="sort-td">Description</th>
            <th class="sort-td">Active</th>
            <th class="sort-td" style="width:9%;">Action</th>
        </tr>
        <?php if (count($cards) > 0) { ?>
            <?php foreach ($cards as $catArr) { ?>
                <tr>
                    <td>
                        <?php 
                            if($catArr['Card']['image']!='')
                                echo $this->Html->image($catArr['Card']['image'], array('style' => 'width:35%;')); 
                            else
                                echo '';
                        ?>
                    </td>
                    <td><?php echo $catArr['Card']['title'] ?> </td>
                    <td>
                        <?php 
                        $cardCatStr = ""; 
                        foreach($catArr['Card']['category'] as $cardCatArr) { 
                            $cardCatStr .= $cardCatArr['name'].', ';
                        } 
                        echo rtrim($cardCatStr, ', ');
                        ?>
                    </td>
                    <td><?php echo $catArr['Card']['description'] ?> </td>
                    <td><?php echo $catArr['Card']['active'] ?></td>
                    <td>
                        <?php echo $this->Html->link('Edit',array('action' => 'editcard','id' => (string) $catArr['Card']['_id'],'full_base' => true)); ?>
                    </td>
                </tr>    
            <?php } ?>
            <tr>
                <td colspan="6">
                    <ul class="pagination">
                        <?php
                        echo $this->Paginator->prev('<<', array('class' => '', 'tag' => 'li'), null, array('class' => 'disabled', 'tag' => 'li'));
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