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
            机器：<?= $host ?> 请选择上线流量
            <br/><br/>
            流量：
            <select id="online_weight_<?= $randomKey ?>" name="weight">
                <option value="100">100</option>
                <option value="50">50</option>
                <option value="30">30</option>
                <option value="10">10</option>
                <option value="0">0</option>
            </select>
            <span class="lbl"></span>
        </label>
    </div>
    <div align="center">
        <button onclick="changeWeight('<?= $taskId ?>','<?= $host ?>','<?= $randomKey ?>')" type="button"
                class="btn btn-primary"
                data-dismiss="modal">
            确定
        </button>

        <button type="button" class="btn btn-primary" data-dismiss="modal">
            取消
        </button>
    </div>
</div>

<script>
    function changeWeight(taskId, host, randomKey) {
        var weight = $('#online_weight_' + randomKey).val();
        $.get('/walle/change-weight', {
            taskId: taskId,
            host: host,
            weight: weight
        }, function (o) {
            if (o.code != 0) {
                alert('code:' + o.code + ' msg:' + o.msg);
            } else {
                host = host.replace(/\./g, '');
                $('#weight_' + host).text(weight + '');
            }
        });
    }
</script>