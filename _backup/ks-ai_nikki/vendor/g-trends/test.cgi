#!/usr/local/bin/python

# ヘッダー情報、テキストファイルを指定
print("Content-Type: text/plain\n")

print('Hello, World!')

from pytrends.request import TrendReq

# pytrendsオブジェクトを生成
pytrends = TrendReq()

# 日本のトレンドデータを取得
trending_data = pytrends.trending_searches(pn='japan')

# 上位10件の急上昇ワードをリストで取得
top_trends = trending_data.head(10).values.flatten().tolist()

# 結果を表示
print(top_trends)