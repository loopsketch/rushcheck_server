### 補足事項
- WSLで利用する場合
  - リポジトリをセーフディレクトリに追加しないとvscodeでgitできない
  ```
  >git config --global --add safe.directory D:/works/sources/rushcheck_server
  ```
  - `docker compose`する場合、`docker/mysql/my.conf`を読込専用に設定しないと、mysqlのコンテナが起動しない
