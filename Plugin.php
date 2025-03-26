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
        
        // ビューを呼び出し
        return $this->pluginView('lending-system', [
            'values' => $values,
            'table_name' => $this->custom_table->table_name,
            'management_column' => $this->custom_view->getCustomOption('management_column'),
            'status_column' => $this->custom_view->getCustomOption('status_column')
        ]);
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
        // 独自設定を追加する場合
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
                ->help('貸出状態を管理する列を選択してください。カスタム列種類「選択肢」「選択肢(値・見出し)」が候補に表示されます。');
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
