<div class="page-header">
    <h1><?php echo $page_title ?></h1>
</div>
<div class="col-sm-12 col-md-12">
    <?php echo $this->Html->link('Csv Download', array('controller' => 'admin', 'action' => 'downloadusers', 'full_base' => true), array('class' => 'btn btn-info')); ?>
</div>
<div class="clearfix">&nbsp;</div>
<div class="table-responsive">
    <table border="0" cellspacing="0" cellpadding="0" class="table-condensed table-bordered table-striped table table-hover">
        <tr>
            <th class="sort-td"><?php echo $this->Paginator->sort('name', 'Name'); ?></th>
            <th class="sort-td">Date Of Birth</th>
            <th class="sort-td">City</th>
            <th class="sort-td">Country</th>
            <th class="sort-td">Gender</th>
            <th class="sort-td">Verified</th>
            <th class="sort-td">Created On</th>
            <th class="sort-td">Active</th>
        </tr>
        <?php if (count($users) > 0) { ?>
            <?php foreach ($users as $uArr) { ?>
                <tr>
                    <td><?php echo isset($uArr['User']['name']) ? $uArr['User']['name'] : ""; ?> </td>
                    <td>
                        <?php
                        if (isset($uArr['User']['dob'])) {

                            if (is_numeric($uArr['User']['dob'])) {

                                $year = substr($uArr['User']['dob'], -4);
                                $dateMonthStr = substr($uArr['User']['dob'], 0, strlen($uArr['User']['dob']) - 4);

                                if (substr($dateMonthStr, -2) > 12) {
                                    $month = '0' . substr($dateMonthStr, -1);
                                    $date = substr($dateMonthStr, 0, strlen($dateMonthStr) - 1);
                                } else {
                                    $month = substr($dateMonthStr, -2);
                                    $date = substr($dateMonthStr, 0, strlen($dateMonthStr) - 2);
                                }

                                $dateStr = $year . '-' . $month . '-' . $date;
                                echo strtoupper(date('d M Y', strtotime($dateStr)));
                            } else {
                                echo $uArr['User']['dob'];
                            }
                        } else {
                            echo '';
                        }
                        ?> 
                    </td>
                    <td><?php echo isset($uArr['User']['city']) ? $uArr['User']['city'] : ""; ?> </td>
                    <td><?php echo isset($uArr['User']['country']) ? $uArr['User']['country'] : ""; ?> </td>
                    <td><?php echo isset($uArr['User']['gender']) ? ($uArr['User']['gender'] == "guy" ? "Male" : "Female") : "Not mentioned"; ?></td>
                    <td><?php echo isset($uArr['User']['verified']) ? ($uArr['User']['verified'] == true ? "yes" : "no") : "no"; ?></td>
                    <td><?php echo date('Y-m-d h:i:s', $uArr['User']['created']->sec); ?></td>
                    <td><?php echo isset($uArr['User']['active']) ? $uArr['User']['active'] : "yes"; ?></td>
                </tr>    
            <?php } ?>
            <tr>
                <td colspan="8">
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
                <td colspan="8">No record found.</td>
            </tr>    
        <?php } ?>
    </table>
</div>