<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <title>QRによる貸出管理システム</title>
    <style>
    .lending-system-container {
        padding: 20px;
        font-family: 'Roboto', sans-serif;
    }

    .box {
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }

    .box:hover {
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    }

    .box-primary {
        border-top: 3px solid #3c8dbc;
    }

    .box-info {
        border-top: 3px solid #00c0ef;
    }

    .box-header {
        padding: 15px;
        border-bottom: 1px solid #f4f4f4;
    }

    .box-title {
        font-size: 18px;
        font-weight: 600;
        color: #444;
    }

    .box-body {
        padding: 20px;
        background-color: #fff;
        border-radius: 0 0 8px 8px;
    }

    .qr-scanner-container {
        margin-bottom: 20px;
        text-align: center;
    }

    #qr-reader {
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
        border: 2px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
    }

    .button-container {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
    }

    .btn {
        padding: 10px 20px;
        font-size: 16px;
        border-radius: 4px;
        transition: all 0.3s ease;
        color: #fff;
        cursor: pointer;
    }

    .btn-primary {
        background-color: #3c8dbc;
        border-color: #367fa9;
    }

    .btn-primary:hover {
        background-color: #367fa9;
    }

    .btn-success {
        background-color: #00a65a;
        border-color: #008d4c;
    }

    .btn-success:hover {
        background-color: #008d4c;
    }

    .btn-info {
        background-color: #00c0ef;
        border-color: #00acd6;
    }

    .btn-info:hover {
        background-color: #00acd6;
    }

    .alert {
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 15px;
    }

    .alert-success {
        background-color: #dff0d8;
        border-color: #d6e9c6;
        color: #3c763d;
    }

    .alert-danger {
        background-color: #f2dede;
        border-color: #ebccd1;
        color: #a94442;
    }

    .alert-info {
        background-color: #d9edf7;
        border-color: #bce8f1;
        color: #31708f;
    }

    .alert-warning {
        background-color: #fcf8e3;
        border-color: #faebcc;
        color: #8a6d3b;
    }

    .table {
        border-radius: 4px;
        overflow: hidden;
        width: 100%;
        border-collapse: collapse;
    }

    .table th, .table td {
        padding: 8px;
        border: 1px solid #ddd;
    }

    .table th {
        background-color: #f9f9f9;
        width: 30%;
    }

    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(0,0,0,0.1);
        border-radius: 50%;
        border-top-color: #3c8dbc;
        animation: spin 1s ease-in-out infinite;
        margin-right: 10px;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    @media (max-width: 767px) {
        .button-container {
            flex-direction: column;
        }
        
        .btn {
            margin-bottom: 10px;
        }
    }
    </style>
</head>
<body>
<div class="lending-system-container">
    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">QRコードスキャナー</h3>
                </div>
                <div class="box-body">
                    <div class="qr-scanner-container">
                        <button type="button" class="btn btn-info" id="start-scan">QRコードをスキャン</button>
                        <div id="qr-reader" style="width: 100%; max-width: 500px; margin: 0 auto; border: 1px solid #ddd; padding: 10px; display: none;"></div>
                        <div id="qr-reader-results"></div>
                    </div>
                    <div class="form-group">
                        <label for="management-number">管理番号</label>
                        <input type="text" class="form-control" id="management-number" placeholder="管理番号を入力または QR コードをスキャン">
                    </div>
                    <div class="form-group">
                        <label for="user-id">利用者 ID</label>
                        <input type="text" class="form-control" id="user-id" value="{{ $login_user_id }}" readonly>
                        <small class="form-text text-muted">現在のログインユーザー: {{ $login_user_name }}</small>
                    </div>
                    <div class="form-group">
                        <label for="status-select">状態選択</label>
                        <select class="form-control" id="status-select">
                            @foreach($status_options as $key => $value)
                                <option value="{{ $key }}">{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="button-container">
                        <button type="button" class="btn btn-primary" id="update-status-button">状態を更新</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">処理結果</h3>
                </div>
                <div class="box-body">
                    <div id="result-container">
                        <div class="alert alert-info">
                            QRコードをスキャンするか、管理番号を入力して貸出または返却ボタンを押してください。
                        </div>
                    </div>
                    <div id="item-details" style="display: none;">
                        <h4>アイテム情報</h4>
                        <table class="table table-bordered">
                            <tbody id="item-details-content">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
$(function() {
    // 変数の初期化
    const tableName = "{{ $table_name }}";
    const managementColumn = "{{ $management_column }}";
    const statusColumn = "{{ $status_column }}";
    const userColumn = "{{ $user_column }}";
    const updateUrl = "{{ $plugin->getFullUrl('update') }}";
    let selectedItem = null;
    
    // CSRFトークンの設定
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    
    // QRコードスキャンボタンのクリックイベント
    $('#start-scan').click(function() {
        $('#qr-reader').show(); // QRリーダーを表示
        Html5Qrcode.getCameras().then(devices => {
            console.log("カメラ一覧:", devices);
            if (devices.length > 0) {
                let cameraId = devices[0].id;
                let html5QrCode = new Html5Qrcode("qr-reader");
                html5QrCode.start(
                    { deviceId: { exact: cameraId } },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    qrCodeMessage => {
                        console.log("QRコード検出:", qrCodeMessage);
                        $('#management-number').val(qrCodeMessage);
                        $('#qr-reader-results').html('<div class="alert alert-success">QRコード読み取り成功: ' + qrCodeMessage + '</div>');
                        html5QrCode.stop(); // 読み取り後にカメラを停止
                        $('#qr-reader').hide(); // QRリーダーを非表示
                        
                        // アイテム検索
                        findItemByManagementNumber(qrCodeMessage);
                    },
                    errorMessage => {
                        console.warn("読み取りエラー:", errorMessage);
                    }
                ).catch(err => {
                    console.error("QRスキャナー起動失敗:", err);
                    $('#qr-reader-results').html('<div class="alert alert-danger">カメラの起動に失敗しました: ' + err + '</div>');
                });
            } else {
                console.log("カメラが見つかりません");
                $('#qr-reader-results').html('<div class="alert alert-warning">カメラが見つかりません。</div>');
            }
        }).catch(err => {
            console.error("カメラ取得エラー:", err);
            $('#qr-reader-results').html('<div class="alert alert-danger">カメラ取得に失敗しました: ' + err + '</div>');
        });
    });
    
    // 管理番号からアイテムを検索
    function findItemByManagementNumber(managementNumber) {
        // 全アイテムから検索
        const items = {!! json_encode($values) !!};
        selectedItem = null;
        
        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            if (item.value[managementColumn] == managementNumber) {
                selectedItem = item;
                break;
            }
        }
        
        if (selectedItem) {
            displayItemDetails(selectedItem);
            $('#result-container').html(`<div class="alert alert-success">アイテムが見つかりました: ${selectedItem.value[managementColumn]}</div>`);
        } else {
            $('#result-container').html(`<div class="alert alert-danger">管理番号 ${managementNumber} のアイテムが見つかりません</div>`);
            $('#item-details').hide();
        }
    }
    
    // アイテム詳細の表示
    function displayItemDetails(item) {
        let html = '';
        
        // アイテムの各プロパティを表示
        for (const key in item.value) {
            html += `<tr>
                <th>${key}</th>
                <td>${item.value[key]}</td>
            </tr>`;
        }
        
        $('#item-details-content').html(html);
        $('#item-details').show();
    }
    
    // 貸出状態更新関数
    function updateLendingStatus(itemId, status, userId) {
        // 処理中表示
        $('#result-container').html(`
            <div class="alert alert-info">
                <span class="loading"></span>処理中...
            </div>
        `);
        
        // 更新処理
        $.ajax({
            url: updateUrl,
            type: 'POST',
            data: {
                id: itemId,
                table_name: tableName,
                status: status,
                user_id: userId,
                status_column: statusColumn,
                user_column: userColumn
            },
            success: function(response) {
                if (response.success) {
                    $('#result-container').html(`<div class="alert alert-success">${response.message}</div>`);
                    
                    // 選択アイテムの状態を更新
                    selectedItem.value[statusColumn] = status;
                    if (userColumn) {
                        selectedItem.value[userColumn] = userId;
                    }
                    
                    // アイテム詳細を再表示
                    displayItemDetails(selectedItem);
                } else {
                    $('#result-container').html(`<div class="alert alert-danger">エラー: ${response.error}</div>`);
                }
            },
            error: function(xhr) {
                let errorMessage = '更新に失敗しました';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMessage += ': ' + xhr.responseJSON.error;
                }
                $('#result-container').html(`<div class="alert alert-danger">${errorMessage}</div>`);
            }
        });
    }
    
    // 利用者IDフィールドの編集防止
    $('#user-id').on('keydown paste', function(e) {
        e.preventDefault();
        return false;
    });
    
    // 状態更新処理
    $('#update-status-button').on('click', function() {
        const managementNumber = $('#management-number').val();
        const userId = $('#user-id').val();
        const selectedStatus = $('#status-select').val();
        const selectedStatusText = $('#status-select option:selected').text();
        
        if (!managementNumber) {
            $('#result-container').html(`<div class="alert alert-danger">管理番号を入力してください</div>`);
            return;
        }
        
        if (!selectedItem) {
            findItemByManagementNumber(managementNumber);
            if (!selectedItem) {
                return;
            }
        }
        
        // 選択された状態に応じた処理
        // 返却等の状態の場合はユーザーIDをクリア、それ以外は現在のユーザーIDを設定
        const isReturnStatus = selectedStatusText.includes('返却') || selectedStatusText === '返却済';
        const userIdToSet = isReturnStatus ? null : userId;
        
        // 状態を更新
        updateLendingStatus(selectedItem.id, selectedStatusText, userIdToSet);
    });
    
    // 管理番号入力時の処理
    $('#management-number').on('change', function() {
        const managementNumber = $(this).val();
        if (managementNumber) {
            findItemByManagementNumber(managementNumber);
        }
    });
});
</script>
</body>
</html>
