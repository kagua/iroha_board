<?php
/**
 * app/View/QuizImports/admin_index.ctp
 * クイズCSVインポート一覧＆アップロードフォーム
 */
?>
<div class="container">
    <!-- インポートフォームパネル -->
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">クイズCSVインポート</h3>
                </div>
                <div class="panel-body">
                    <?php echo $this->Form->create('QuizImport', [
                        'url' => ['action' => 'import', 'admin' => true],
                        'type' => 'file',
                        'class' => 'form-horizontal'
                    ]); ?>

                    <div class="form-group">
                        <?php echo $this->Form->label('course_id', 'コース', ['class' => 'col-sm-3 control-label']); ?>
                        <div class="col-sm-9">
                            <?php echo $this->Form->select('course_id', 
                                Hash::combine($courses, '{n}.Course.id', '{n}.Course.title'), 
                                ['empty' => 'コースを選択してください', 'class' => 'form-control', 'required' => true]
                            ); ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <?php echo $this->Form->label('csv_file', 'CSVファイル', ['class' => 'col-sm-3 control-label']); ?>
                        <div class="col-sm-9">
                            <?php echo $this->Form->file('csv_file', ['accept' => '.csv', 'required' => true]); ?>
                            <p class="help-block">
                                CSVフォーマット: 問題タイトル,問題文,解答タイプ(single/multiple/text),選択肢1,...,正解,配点,解説
                            </p>
                            <div class="sample-download">
                                <?php echo $this->Html->link(
                                    '<span class="glyphicon glyphicon-download"></span> サンプルCSVダウンロード',
                                    ['action' => 'download_sample', 'admin' => true],
                                    ['escape' => false, 'class' => 'btn btn-info btn-sm']
                                ); ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-sm-offset-3 col-sm-9">
                            <?php echo $this->Form->button('インポート', ['class' => 'btn btn-primary']); ?>
                        </div>
                    </div>

                    <?php echo $this->Form->end(); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- アップロード済みインポート一覧 -->
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">インポート履歴</h3>
                </div>
                <div class="panel-body">
                    <?php if (!empty($imports)): ?>
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>コース</th>
                                <th>ファイル名</th>
                                <th>登録日時</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($imports as $import): ?>
                            <tr>
                                <td><?php echo h($import['QuizImport']['id']); ?></td>
                                <td><?php echo h($import['Course']['title']); ?></td>
                                <td><?php echo h($import['QuizImport']['filename']); ?></td>
                                <td><?php echo h($import['QuizImport']['created']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p>まだインポートされたデータはありません。</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
