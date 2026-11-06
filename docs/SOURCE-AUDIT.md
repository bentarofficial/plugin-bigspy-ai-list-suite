# G-81: Đối chiếu nguồn mã

Ngày 06-11-2026. So sánh ba repo riêng (nhánh `main`) với `theme-bigspy-ai-list/plugin/` (commit `0f7b8bf`, 03-10-2026).

| Plugin | Repo riêng | Bản trong repo theme | Khác biệt |
| --- | --- | --- | --- |
| BIGSPY AI LIST Generator | 2.9.8 (`12292ac`, tag v2.9.8) | 2.9.9 | Số phiên bản, một dòng mô tả Internal Links, `internal-link-history.js` (bỏ panel "Saved internal links", các dòng là danh sách lưu, không chép vào localStorage) |
| BIGSPY AI Post Writer | 1.2.3 (`70781b9`, tag v1.2.3) | 1.2.4 | Số phiên bản, một dòng mô tả Internal Anchors |
| BigSpy Site Insights | 0.3.0 (`1e3b938`, tag v0.3.0) | 0.3.0 | Giống hệt từng file |

Không có thay đổi nào chỉ nằm ở repo riêng: bản trong repo theme là tập cha, mọi khác biệt là phần 2.9.9/1.2.4 được thêm sau. Repo riêng không có nhánh khác ngoài `main`.

## Chốt nguồn gốc

- Gốc gộp: `theme-bigspy-ai-list/plugin/` (Generator 2.9.9, Post Writer 1.2.4, Site Insights 0.3.0).
- Repo riêng chỉ để tham khảo lịch sử; không chép lịch sử commit sang repo công khai.

## Rà trước khi công khai

- Không thấy API key, mật khẩu, email hay dữ liệu riêng trong mã. Key Kyma, Serper, Google, OpenPageRank chỉ đọc từ option.
- Host ngoài: `kymaapi.com`, `google.serper.dev`, `chromeuxreport.googleapis.com`, `openpagerank.com`, `tranco-list.eu`, `data.iana.org` (dịch vụ công khai).
- Cần đổi khi gộp: User-Agent của Site Insights `BigSpySiteInsights/0.1; +https://bigspyai.com` thành tên và URL của List Suite; `Author: By Bentar` thống nhất; rà lại system prompt mặc định ở G-83 để không mang nội dung riêng của bigspyai.com.
