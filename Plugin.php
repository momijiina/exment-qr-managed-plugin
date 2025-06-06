<?php

namespace App\Plugins\LendingSystem;

use Exceedone\Exment\Services\Plugin\PluginViewBase;
use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomTable;

class Plugin extends PluginViewBase
{
    protected $useCustomOption = true;
    /**
     * 一覧表示時のメソッド。"grid"固定
     */
    public function grid()
    {
        $values = $this->values();
        
        // 現在のログインユーザー情報を取得
        $login_user = \Exment::user();
        $login_user_id = $login_user->base_user->id ?? null;
        $login_user_name = $login_user->base_user->name ?? null;
        
        // 貸出状況等の選択肢の取得
        $status_column_name = $this->custom_view->getCustomOption('status_column');
        $status_options = [];
        
        if (!empty($status_column_name)) {
            $status_column = $this->custom_table->custom_columns->first(function ($column) use ($status_column_name) {
                return $column->column_name == $status_column_name;
            });
            
            if ($status_column) {
                $status_options = $status_column->createSelectOptions();
            }
        }
        
        // ビューを呼び出し
        return $this->pluginView('lending-system', [
            'values' => $values,
            'table_name' => $this->custom_table->table_name,
            'management_column' => $this->custom_view->getCustomOption('management_column'),
            'status_column' => $status_column_name,
            'user_column' => $this->custom_view->getCustomOption('user_column'),
            'plugin' => $this->plugin,
            'login_user_id' => $login_user_id,
            'login_user_name' => $login_user_name,
            'status_options' => $status_options
        ]);
    }

    /**
     * このプラグイン独自のエンドポイント
     * 貸出状況と貸出者の更新処理
     */
    public function update()
    {
        try {
            // リクエストからデータを取得
            $id = request()->get('id');
            $table_name = request()->get('table_name');
            $status = request()->get('status');
            $user_id = request()->get('user_id');
            
            // 必須パラメータのバリデーション
            if (empty($table_name) || empty($id)) {
                return response()->json(['error' => 'Missing required parameters'], 400);
            }
            
            // テーブルとデータの取得
            $custom_table = CustomTable::getEloquent($table_name);
            if (!$custom_table) {
                return response()->json(['error' => 'Table not found: ' . $table_name], 404);
            }
            
            $custom_value = $custom_table->getValueModel($id);
            if (!$custom_value) {
                return response()->json(['error' => 'Value not found: ' . $id], 404);
            }
            
            // 貸出状況列の取得
            $status_column = request()->get('status_column');
            
            // 値の更新
            $updates = [];
            
            // 貸出状況の更新
            if (!empty($status) && !empty($status_column)) {
                $updates[$status_column] = $status;
            }
            
            // 貸出者の更新（ユーザーIDが指定されている場合）
            $user_column = request()->get('user_column');
            if (!empty($user_column)) {
                $updates[$user_column] = $user_id; // 空の場合はnullが設定される
            }
            
            // 値を設定して保存
            $custom_value->setValue($updates)->save();
            
            return response()->json([
                'success' => true,
                'message' => '貸出状況が更新されました',
                'data' => $custom_value
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ビュー設定画面で表示するオプション
     * Set view option form for setting
     *
     * @param Form $form
     * @return void
     */
    public function setViewOptionForm($form)
    {
        $form->embeds('custom_options', '詳細設定', function($form) {
            // カラム一覧を取得する別の方法を試す
            $columns = [];
            foreach ($this->custom_table->custom_columns as $column) {
                $columns[$column->column_name] = $column->column_name;
            }
            
            $form->select('management_column', '管理番号列')
                ->options($columns)
                ->required()
                ->help('管理番号として使用する列を選択してください。');
                
            // 選択肢タイプのカラムを取得
            $selectColumns = [];
            foreach ($this->custom_table->custom_columns as $column) {
                if (in_array($column->column_type, [ColumnType::SELECT, ColumnType::SELECT_VALTEXT])) {
                    $selectColumns[$column->column_name] = $column->column_name;
                }
            }
            
            $form->select('status_column', '貸出状態列')
                ->options($selectColumns)
                ->required()
                ->help('貸出状態等を管理する列を選択してください。カスタム列種類「選択肢」「選択肢(値・見出し)」が候補に表示されます。');
            
            // ユーザー列の設定を追加
            $userColumns = [];
            foreach ($this->custom_table->custom_columns as $column) {
                if (in_array($column->column_type, [ColumnType::USER])) {
                    $userColumns[$column->column_name] = $column->column_name;
                }
            }
            
            $form->select('user_column', '貸出者列')
                ->options($userColumns)
                ->help('貸出者を記録する列を選択してください。カスタム列種類「ユーザー」が候補に表示されます。');
        });
        
        // フィルタ(絞り込み)の設定を行う場合
        static::setFilterFields($form, $this->custom_table);
        
        // 並べ替えの設定を行う場合
        static::setSortFields($form, $this->custom_table);
    }

    /**
     * プラグインの編集画面で設定するオプション。全ビュー共通で設定する
     *
     * @param [type] $form
     * @return void
     */
    public function setCustomOptionForm(&$form)
    {
        // 必要な場合、追加
    }

    // 以下、貸出・返却システムで必要な処理 ----------------------------------------------------
    
    protected function values()
    {
        $query = $this->custom_table->getValueQuery();
        
        // データのフィルタを実施
        $this->custom_view->filterModel($query);
        
        // データのソートを実施
        $this->custom_view->sortModel($query);
        
        // 値を取得
        $items = collect();
        $query->chunk(1000, function($values) use(&$items) {
            $items = $items->merge($values);
        });
        
        return $items;
    }
}
