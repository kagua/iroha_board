<?php
/**
 * クイズCSVインポート処理
 * IrohaBoard用
 */
App::uses('AppController', 'Controller');

class QuizImportsController extends AppController
{
    public $uses = array('Quiz', 'Course', 'Choice');
    
    /**
     * 事前処理
     */
    public function beforeFilter()
    {
        parent::beforeFilter();
        
        // 管理者以外のアクセスを拒否
        if(!$this->Auth->user('role_id') == 1 && !$this->Auth->user('role') == 'admin')
        {
            $this->redirect($this->Auth->logout());
            return;
        }
    }
    
    /**
     * 管理者向けインポートフォーム表示
     */
    public function admin_index()
    {
        // コース一覧を取得
        $this->loadModel('Course');
        $courses = $this->Course->find('all', array(
            'order' => array('Course.title' => 'asc')
        ));
        
        $this->set(compact('courses'));
    }
    
    /**
     * 管理者向けインポート処理
     */
    public function admin_import()
    {
        if($this->request->is('post'))
        {
            $course_id = $this->request->data['course_id'];
            
            // コースの存在確認
            $course = $this->Course->findById($course_id);
            
            if(!$course)
            {
                $this->Flash->error(__('コースが見つかりません'));
                return $this->redirect(array('action' => 'index', 'admin' => true));
            }
            
            // ファイルのアップロード確認
            if(empty($_FILES['csv_file']['tmp_name']))
            {
                $this->Flash->error(__('ファイルをアップロードしてください'));
                return $this->redirect(array('action' => 'index', 'admin' => true));
            }
            
            // CSVファイル読み込み
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            
            if($handle === false)
            {
                $this->Flash->error(__('ファイルを開けませんでした'));
                return $this->redirect(array('action' => 'index', 'admin' => true));
            }
            
            // 文字コード変換
            $csv_data = array();
            while(($row = fgetcsv($handle)) !== false)
            {
                // SJIS-winからUTF-8に変換
                foreach($row as &$cell)
                {
                    $cell = mb_convert_encoding($cell, 'UTF-8', 'SJIS-win');
                }
                $csv_data[] = $row;
            }
            
            fclose($handle);
            
            // インポート処理
            $success_count = 0;
            $error_count = 0;
            
            $this->Quiz->begin();
            
            foreach($csv_data as $i => $row)
            {
                // ヘッダー行はスキップ
                if($i == 0 && preg_match('/タイトル|問題文/u', $row[0]))
                {
                    continue;
                }
                
                // データ不足チェック
                if(count($row) < 5)
                {
                    $error_count++;
                    continue;
                }
                
                $title = trim($row[0]);
                $question = trim($row[1]);
                $type = trim($row[2]);
                
                // 選択肢の数を動的に判定
                $choices = array();
                $choice_index = 3;
                $correct_index = 0;
                
                while(isset($row[$choice_index]) && !empty($row[$choice_index]) && $choice_index < count($row) - 2)
                {
                    $choices[] = trim($row[$choice_index]);
                    $choice_index++;
                }
                
                $correct = trim($row[$choice_index]);
                $choice_index++;
                $point = isset($row[$choice_index]) ? intval($row[$choice_index]) : 1;
                $choice_index++;
                $explanation = isset($row[$choice_index]) ? trim($row[$choice_index]) : '';
                
                // データ保存
                try {
                    // クイズデータの作成
                    $quiz_data = array(
                        'Quiz' => array(
                            'course_id' => $course_id,
                            'user_id' => $this->Auth->user('id'),
                            'title' => $title,
                            'question' => $question,
                            'type' => $type,
                            'point' => $point,
                            'explanation' => $explanation,
                            'status' => 1, // 公開状態
                            'sort_no' => $this->Quiz->getNextSortNo($course_id),
                            'created' => date('Y-m-d H:i:s'),
                            'modified' => date('Y-m-d H:i:s')
                        )
                    );
                    
                    $this->Quiz->create();
                    $this->Quiz->save($quiz_data);
                    $quiz_id = $this->Quiz->getLastInsertID();
                    
                    // 選択肢がある場合
                    if($type == 'single' || $type == 'multiple')
                    {
                        foreach($choices as $j => $choice_text)
                        {
                            $correct_flag = 0;
                            
                            // 正解チェック
                            if($type == 'single')
                            {
                                // 単一選択の場合
                                if($correct == ($j + 1))
                                {
                                    $correct_flag = 1;
                                }
                            }
                            else if($type == 'multiple')
                            {
                                // 複数選択の場合
                                $correct_array = explode(',', $correct);
                                if(in_array(($j + 1), $correct_array))
                                {
                                    $correct_flag = 1;
                                }
                            }
                            
                            $choice_data = array(
                                'Choice' => array(
                                    'quiz_id' => $quiz_id,
                                    'title' => $choice_text,
                                    'correct' => $correct_flag,
                                    'created' => date('Y-m-d H:i:s'),
                                    'modified' => date('Y-m-d H:i:s')
                                )
                            );
                            
                            $this->Choice->create();
                            $this->Choice->save($choice_data);
                        }
                    }
                    else if($type == 'text')
                    {
                        // テキスト入力問題の場合、正解をChoiceテーブルに追加
                        $choice_data = array(
                            'Choice' => array(
                                'quiz_id' => $quiz_id,
                                'title' => $correct,
                                'correct' => 1,
                                'created' => date('Y-m-d H:i:s'),
                                'modified' => date('Y-m-d H:i:s')
                            )
                        );
                        
                        $this->Choice->create();
                        $this->Choice->save($choice_data);
                    }
                    
                    $success_count++;
                } catch(Exception $e) {
                    $error_count++;
                }
            }
            
            if($error_count == 0)
            {
                $this->Quiz->commit();
                $this->Flash->success(sprintf(__('%d問のクイズをインポートしました'), $success_count));
            }
            else
            {
                if($success_count > 0)
                {
                    $this->Quiz->commit();
                    $this->Flash->warning(sprintf(__('%d問のクイズをインポートしました。%d問のインポートに失敗しました。'), $success_count, $error_count));
                }
                else
                {
                    $this->Quiz->rollback();
                    $this->Flash->error(__('クイズのインポートに失敗しました'));
                }
            }
            
            return $this->redirect(array('controller' => 'quizzes', 'action' => 'index', $course_id, 'admin' => true));
        }
        
        return $this->redirect(array('action' => 'index', 'admin' => true));
    }
    
    /**
     * サンプルCSVダウンロード
     */
    public function admin_download_sample()
    {
        $this->autoRender = false;
        
        // CSVデータ作成
        $data = array(
            array('問題タイトル', '問題文', '解答タイプ', '選択肢1', '選択肢2', '選択肢3', '選択肢4', '正解', '配点', '解説'),
            array('単一選択問題', 'これは単一選択の問題です。正しいものを1つ選んでください。', 'single', '選択肢1', '選択肢2', '選択肢3', '選択肢4', '2', '1', '解説文をここに書きます。'),
            array('複数選択問題', 'これは複数選択の問題です。正しいものをすべて選んでください。', 'multiple', '選択肢1', '選択肢2', '選択肢3', '選択肢4', '1,3', '2', '複数の正解がある場合は、カンマで区切ります。'),
            array('テキスト入力問題', 'これはテキスト入力の問題です。答えを入力してください。', 'text', '', '', '', '', 'こたえ', '1', 'テキスト問題の場合、正解のテキストを設定します。')
        );
        
        // ファイル出力
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="sample_quiz.csv"');
        
        $output = fopen('php://output', 'w');
        
        // SJIS-winに変換
        foreach($data as $row)
        {
            foreach($row as &$cell)
            {
                $cell = mb_convert_encoding($cell, 'SJIS-win', 'UTF-8');
            }
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
}