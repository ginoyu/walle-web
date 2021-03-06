<?php
/**
 * @var yii\web\View $this
 */
$this->title = '流量选择';
$randomKey = time();
?>
<div>
    <div>
        <label class="text-left bolder blue">
            机器：<?= $host ?> 确认是否手动测试通过，通过请选择上线流量
            <br/><br/>
            流量：
            <select id="online_weight_<?= $randomKey ?>" name="weight">
                <option value="100">100</option>
                <option value="50">50</option>
                <option value="30">30</option>
                <option value="10">10</option>
                <option value="0">0</option>
            </select>
            <br/><br/>
            <div class="form-group" style="display: <?= $last ? 'none' : '' ?>">
                是否立即开始上线下一个机器:
                <input id="online_next_<?= $randomKey ?>" value="1"
                       type="checkbox" checked
                       class="ace ace-switch ace-switch-5"
                       data-rel="tooltip" data-title="是否立即开始上线下一个机器" data-placement="right">
                <span class="lbl"></span>
            </div>
            <span class="lbl"></span>
        </label>
    </div>
    <br/>
    <div align="center">
        <button type="button" class="btn btn-primary" data-dismiss="modal"
                onclick="pass_click('<?= $host ?>','<?= \app\components\TaskStateManager::getManualResultKey($taskId, $host) ?>','<?= $randomKey ?>')">
            确定
        </button>

        <button type="button" class="btn btn-primary" data-dismiss="modal">
            取消
        </button>
    </div>
</div>

<script>

    function pass_click(host, randomKey, key) {
        var weight = $('#online_weight_' + key).val();
        var continueNext = document.getElementById('online_next_' + key).checked;
        $.get('/walle/notify-test-result', {
            success: true,
            randomKey: randomKey,
            weight: weight,
            continue: continueNext,
            taskId:<?=$taskId?>
        }, function (o) {
            // alert(JSON.stringify(o));
            if (o.code != 0) {
                alert('code:' + o.code + ' msg:' + o.msg);
            } else {
            }
        });
    }

</script>