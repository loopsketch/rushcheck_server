### 補足事項
- WSLで利用する場合
  - リポジトリをセーフディレクトリに追加しないとvscodeでgitできない
  ```
  >git config --global --add safe.directory D:/works/sources/rushcheck_server
  ```
  - `docker compose`する場合、`docker/mysql/my.conf`を読込専用に設定しないと、mysqlのコンテナが起動しない

### テスト用
- テスト用のDBを作成してからテストを実行する
  ```
  > create database rushcheck character set utf8mb4;
  > grant all privileges on rushcheck_test.* to rushcheck;
  ```
