---
title: "Recorded verification artifacts"
type: reference
status: generated
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
tags: [documentation, index]
---
# Recorded verification artifacts

This inventory records available result/check files and their source hashes. It does not rerun them or interpret every object as a passing test. Observation results may reproduce a defect. Raw local-data records and credentials are not imported.

| Repository artifact | Shape of recorded result | SHA-256 |
| --- | --- | --- |
| `writable/benefit-promo-review/package-checks.json` | Object keys: file, files, bytes, sha256, entries | `0a17bfd2c4eef7cbe3aabe12c8258c299dfc1d4014f798e5f767e0bd17d97b8e` |
| `writable/cashier-day-review/browser-checks.json` | 96 recorded entries | `91c9734f0ef258028a9d40073e3ea3aa3e9744145910db9efed6293ebd8d543a` |
| `writable/cashier-day-review/datatable-checks.json` | 15 recorded entries | `d86d9a10ab125411b2efa7598d446ff55a982127ac0d624002c35fbe5d86ac35` |
| `writable/cashier-day-review/mysql-checks.json` | Object keys: passed, live_sales_modified | `4da9b67055e34cdcb069b1e74cc11fb21eeda7ca9a1b0101808c45d4589fc300` |
| `writable/cashier-day-review/package-checks.json` | Object keys: file, files, bytes, sha256, source_hashes_verified, invoice_configuration_excluded | `4661b66e9b9c920713822630b91b48008480059f095bea59fd7f59bbd9345eac` |
| `writable/cashier-day-review/shift-table-checks.json` | 13 recorded entries | `00210cc8dd7c2ca443164af331ccd9a23daa303d7c461b777cc357a8aa27f301` |
| `writable/cashier-fk-review/local-upgrade-results.json` | Object keys: database, backup, backup_bytes, backup_sha256, imports, existing_data_tables_preserved, prior_migrations_preserved, migration_000017_count, constraints, before | `7e9fa96672c942a0767f933e2b1145efffdcb45ae37b46aa6c29ac94bc7c7f5b` |
| `writable/cashier-fk-review/mysql-results.json` | Object keys: checks, scratch_database_removed, application_records_changed | `19d4bd005b68aa21c1a1d6b01b715c390ea21d59b177f508c3a082b9c03148a7` |
| `writable/cashier-session-review/local-application-results.json` | Object keys: database, schema_applied, repeated_import_passed, protected_existing_tables, existing_application_records_preserved, migration, hosted_database_changed | `7c8098676b1148f1b99620be88774f2353b3b0fe7fbc6a5fdc9fc0c7ed417ae4` |
| `writable/cashier-session-review/mysql-results.json` | Object keys: checks, competing_processes, cashier_successful_claims, administrator_successful_claims, application_database_changed | `5b63886496ae5eceb1a78f00c831f0fbe2fff3e50a0884f542cca722c4c4436b` |
| `writable/damaged-items-review/browser-results.json` | Object keys: checks, javascript_errors, application_records_changed | `b86ef7a186e87df93836413130f50f55807b71674da39b130052973bcef03d35` |
| `writable/damaged-items-review/mysql-results.json` | Object keys: checks, damaged_records, damaged_units, quarantine_inspections_included, table_hashes_preserved, database_writes, hosting_changed | `276710ca260700956a45bb0f517a48de7c14f16fee0dc9bf2982c4c7991f1f16` |
| `writable/export-audit-20261023/docs-check.json` | Not valid JSON; inspect original | `6f4135e3b12f9ecea5b0c920b9bc3a7ae0390765c704a7fa3f93473f9b9b40fe` |
| `writable/export-audit-20261023/http-results.json` | Object keys: checks, downloads, failures | `898271d2e2666f7ee1fcc122a3db96618663eda986501e56ad258e441675de4e` |
| `writable/export-audit-20261023/pdf-results.json` | Object keys: files, pages, results | `c3af6fc77f151c217c469b29c39ecac7af0589605bb9bb55e4a8ef2db8034fd3` |
| `writable/export-audit-20261023/print-pdf-results.json` | 5 recorded entries | `e77b6024745836b1e8acd6a21150f4588dc3ecd592be01c4b941ae53e607d2b2` |
| `writable/export-audit-20261023/print-results.json` | Object keys: results, errors | `ccefda83685ade0363d5ede106794a1285d07665492ae2e3a211efe5d470e5e2` |
| `writable/export8-fk-review/verification-results.json` | Object keys: source, source_sha256, export_server, verification_server, statement_counts, checks, counts, before, after, orphan_counts | `666bd8270916667656019ff5880f0d9e440da5f076035a8cf2b9ea6c09414482` |
| `writable/latest-update-audit/fixed-browser-results.json` | Object keys: simulated_http_502, vat_rounding | `a7c2bc4632c6626513d6a3d35d262083e8f00e654a75ef9948863e63e073d7b9` |
| `writable/latest-update-audit/fixed-probe-results.json` | Object keys: old_checkout_retry_clears_new_cart, equal_total_correction_missing_from_day, rounding_combination_misses_better_total | `59fbd39fb693969e91309fb97c47f4d42e716a4d3d31bda8074e2f0c99633680` |
| `writable/latest-update-audit/local-data-results.json` | Object keys: audited_on, read_only, database, available, counts, master_stock_mismatches, over_refunded_lines, missing_delivery_snapshots, refund_overage_details, noncash_missing_reference | `a598893e82c08b9f5f8a184bff9b00c63b49ce90058b5c591eaba7b0d6ea02e3` |
| `writable/latest-update-audit/probe-results.json` | Object keys: old_checkout_retry_clears_new_cart, equal_total_correction_missing_from_day, rounding_combination_misses_better_total | `012e92b0b9e4c5ac566853fdface016dcceeb5946faf77e3856da264b87aee8a` |
| `writable/latest-update-audit/sale41-refund-reconciliation-result.json` | Object keys: sale_id, before_refunds, after_refunds, refund_excess, corrected_refund_ids, unchanged, repeat_import_no_op, backup, database_backup_table, after | `0b928922c081e9a92751ccca0587af531a00eb716eb937f1a1d1b464f09c1756` |
| `writable/latest-update-audit/stock-reconciliation-result.json` | Object keys: reconciled, remaining_mismatches, changes, backup | `b3ebae8ba6392e218d1c655f3c6d4bd582da4b247247649712e5dbfc14c8bd38` |
| `writable/login-history-review/browser-results.json` | Object keys: checks, javascript_errors, application_records_changed | `c4c1d513bb8c78b20eb617e01d76b9becdac1eab97705466a5229451b8e795c5` |
| `writable/management-form-review/browser-checks.json` | Object keys: checks, posts, application_database_accessed | `4e6cbed05735a65ab59c379559b7e0f3fb79ef22bda0c2027f9510ac7a9e748d` |
| `writable/medicine-type-review/benefit-promo-checks.json` | 42 recorded entries | `80cbca08ad43446e9be7da689635d5e1c1d59964741c05f56df11213be5f6f61` |
| `writable/medicine-type-review/checkout-feedback-checks.json` | 21 recorded entries | `97e57ca27ed7f91c5e3118970c093bf8fba87323fbcf121d8f340e2c6f6382d7` |
| `writable/medicine-type-review/checks.json` | 99 recorded entries | `ef4d02161d302481658965e4ebb260110e10912d466ce431164152a5eb67624a` |
| `writable/medicine-type-review/product-promo-checks.json` | 13 recorded entries | `c8ead578f3c8140e3481631101e004635b776a4f211a9ffcd5cfc4eb17c2e970` |
| `writable/medicine-type-review/sale-details-checks.json` | 24 recorded entries | `9aa0026e5825dcc09cd73ebcae212d2e73a6113edc1bbfd20699647987d8cbf6` |
| `writable/pdf-export-review/check-results.json` | Object keys: pdf_samples, pages, all_A4_landscape, all_page_numbers, stock_log_ids_complete, expiry_skus_complete, confirmed_financial_presentation_gap, confirmed_missing_table_heading, method | `e0efb8a5ac010caaf2e9fec7cc82bd18568fb472e73bfda1f656e14cb2485c9e` |
| `writable/pdf-export-update/check-results.json` | Object keys: samples, pages, A4_landscape, all_running_identity_and_page_numbers, stock_records, expiry_records, complete_long_note_repetitions, continuation_headings_verified, empty_sales_one_page, summary_variants_one_page | `6337c66fb642a700af18e44baaa9cc0fd8f8004d6084a3439447d31ba232fbb8` |
| `writable/pdf-export-update/package-checks.json` | Object keys: file, files, bytes, sha256, source_hashes_verified | `9956b26470cd7655494f3b95e2f393167e58ad6eec4741df5a678c3d6a9dc83b` |
| `writable/pos-ajax-review/browser-results.json` | Object keys: checks, posts, javascript_errors, application_database_accessed | `e688d18853089661796fc60252565d4271811a3098d2e1ed083daca58e36c043` |
| `writable/pos-ajax-review/browser-session-results.json` | Object keys: checks, posts, javascript_errors, application_database_accessed | `154a6bf1d3cb85b57378f038502e7df7093d09cd295ada225228c0e0cee93794` |
| `writable/pos-checkout-review/package-checks.json` | Object keys: file, files, bytes, sha256, entries | `ce05c25bf3e85aae8b24f14bf1b005f90465859449bcb9ef9b5c7afaf589a48b` |
| `writable/pos-inventory-audit/local-data-results.json` | Object keys: database, read_only, audited_on, counts, negative_stock, master_stock_mismatches, master_stock_mismatch_details, duplicate_inventory_pairs, orphan_sale_items, duplicate_invoices | `68b8efeca83e8a9a63230239bdb610449b2f52e42d60a996edca17a02d6540fe` |
| `writable/pos-inventory-audit/probe-results.json` | Object keys: same_receive_request_twice, gcash_overpayment_no_reference, card_overpayment_no_reference | `3fb01ccffb316866448322a1a46c8f0729b763ccc1db7276d9e41086e61449ef` |
| `writable/product-create-review/browser-checks.json` | Object keys: checks, posted_count, application_database_accessed | `f96eb6ff87d303bcae4165afe5a31bf4d06b361de8013802fe8e5551930e58e3` |
| `writable/promo-review/package-checks.json` | Object keys: file, files, bytes, sha256, entries | `b277091707a9f069409a93eff5b3531c2449e3a33d288f6a0fcd0771b7b759ba` |
| `writable/quarantine-review/browser-results.json` | Object keys: checks, javascript_errors, application_records_changed | `03de2ba2b1ef351cead72ff47f1fd8afdb08e6c79d6e84cbade3ddc155838500` |
| `writable/quarantine-review/local-upgrade-results.json` | Object keys: database, backup, backup_bytes, backup_sha256, imports, existing_data_tables_preserved, prior_migrations_preserved, migration_000018_count, pending_returns, before | `1d5c8cdeea70635fee0d40a7cb2903b8be79b2a0b83df739618dc621bfaf3e2b` |
| `writable/quarantine-review/mysql-results.json` | Object keys: checks, source_business_records_preserved, source_database_written, concurrent_inspectors | `e0b5765d03d2576cc608cf08d9b8b59e3d90259eb2b06fac589b217b2bf8e32a` |
| `writable/receipt-invoice-review/checks.json` | 42 recorded entries | `50e694ccdc1d9f1b7c723604a26754d8da426d7e9b226235b5beebe6341737d2` |
| `writable/receipt-review/category-checks.json` | 32 recorded entries | `b1c904b5ce3ee089d43648ce96e9ad8e6c247f20e27c39f194f913ef10372924` |
| `writable/receipt-review/checks.json` | 66 recorded entries | `f758fe523f030c6cf611cc97d323282877285ce53f560440c5885ccf5ddfb0d1` |
| `writable/receipt-review/dashboard-checks.json` | 30 recorded entries | `4a9584f6d9ad209cc06962c85061eeeab00e9a49c8c75e533e9aa621e8dfcf3d` |
| `writable/receipt-review/discount-checks.json` | 6 recorded entries | `a6823e804c8f096ae169953f5035873b75019a02f334918bf676589b58871afc` |
| `writable/receipt-review/forecast-manual-checks.json` | 4 recorded entries | `9eefb3ea56bf544751a8bdf6cc83b9c359a9892f2be83c1d84eba56af2cbd8c3` |
| `writable/receipt-review/interface-audit-results.json` | Object keys: typography, spacing, contrast, failedTopbarSave, afterReload, cancelledNavigation, mobileText, accountIsolation, sampleInput | `b75a622fd5ee8a4caf7802a8c4370b4378f5da4ae0037868b65872a81d1bc2c7` |
| `writable/receipt-review/login-checks.json` | 30 recorded entries | `6dc49cde5cfc9adab06e1a94136ed48fd4c68d07a971ecf5a433191bfd15ea25` |
| `writable/receipt-review/modal-checks.json` | 36 recorded entries | `c29a6cecd5fef46143e78d5ba1f58a99cdebefe65786235800f7d220f736dd21` |
| `writable/receipt-review/pagination-checks.json` | 48 recorded entries | `b9ea61f058b9d8892042865dc8d32e6637f369ccedae424d8b739770245364f2` |
| `writable/receipt-review/pos-checks.json` | 68 recorded entries | `be196c507db78709aaad8c47521a1210f3327101788b94c6a008c4444d2efa8b` |
| `writable/receipt-review/reports-ui-checks.json` | 36 recorded entries | `00911f0815a894f7c54708b164110897ee87b9740eac78bedd30ab8f76d5cc84` |
| `writable/receipt-review/restock-checks.json` | 24 recorded entries | `6c0443147b5483178b16a06312291c0051703737466a6cc8da1684545c90a79c` |
| `writable/receipt-review/settings-checks.json` | 20 recorded entries | `69ebca15b425673fb0305a34f86af996692fcc9e733107ae335035fd109e79dc` |
| `writable/receipt-review/sidebar-checks.json` | 20 recorded entries | `5d81a6e79f75d48762efb0ce228d36d0f15d5972fd5823738849c8c16bd6feb8` |
| `writable/reports-reconciliation-review/browser-checks.json` | 18 recorded entries | `f0e218f7389b08541f17a4597545cac987cecbb952000083dbfa58072f5cc630` |
| `writable/return-access-review/browser-results.json` | Object keys: checks, javascript_errors, application_records_changed | `8e66a5de984b21a76c47d6b5a2ec350e81cdcd590502fab408206812ac8862d3` |
| `writable/return-access-review/mysql-results.json` | Object keys: checks, cashiers_checked, cross_branch_details_checked, table_hashes_preserved, database_writes, hosting_changed | `722d9d33ebc313b87cf4e7cd104de4be2cfa21f4816099112ec1516f236c532b` |
| `writable/sales-history-review/browser-results.json` | Object keys: checks, javascript_errors, application_database_accessed | `0c22b69f1caceebd72d1d7d4877e2e5ee09adf32bfcd888bfdf106c5d831219a` |
| `writable/server-clock-review/browser-results.json` | Object keys: checks, javascript_errors, clock_requests, real_local_clock_endpoint_used, authenticated_requests, application_records_changed | `b4befd3b818febc68a38b2cd4b3e25c83164d99de55dab708b351417c106c64e` |
| `writable/shift-print-review/browser-results.json` | 5 recorded entries | `6a6c746a53b00473e8e89eac8ec1ed3330454722d641320f105ebeece4cb8162` |
| `writable/shift-print-review/package-checks.json` | Object keys: file, files, bytes, sha256, source_hashes_verified | `8e75489ad6d6e3e7d3abc80a9ab229b6ba6900f3a871474bd6237e0487c0f4cd` |
| `writable/shift-reset-review/checks.json` | Object keys: source_sha256, cleaned_sha256, shifts_removed, actions_removed, shift_reviews_removed, preserved_row_counts, verified, local_application_database_modified | `87fa72605bcaaf612e2963aac774da9077bf2541dbb8112b9d9abfb7af9f90c2` |
| `writable/stock-adjustment-review/browser-results.json` | Object keys: checks, fixture_posts, javascript_errors, application_database_accessed | `df0b116e802124a03c1ea284451bce75d1f16f72e511389e0adaaded1a778bae` |
| `writable/submit-review-review/browser-results.json` | Object keys: checks, confirmed_fixture_posts, javascript_errors, application_database_accessed | `2abdffa4b5179359845bc95759b1d3b19ae1b882c22185d9fe677eca5045f756` |
| `writable/transaction-overview-review/browser-results.json` | Object keys: checks, javascript_errors, fixture_posts, application_records_changed | `ee5d115f7ea649c4a107a030ae2d7e6a2cfa530920b5b427494b112ec6720e13` |
| `writable/user-name-update/result.json` | Object keys: database, updated, changes, backup, all_other_user_fields_verified_unchanged | `66106f161581523b6a078ad61a9cdf3febb5ca0e347b715c80981da0b6c31f79` |
| `writable/user-presence-review/browser-results.json` | Object keys: checks, javascript_errors, application_records_changed | `b144634410050373aed6bb17686a78df81f55a53dd5d33f4103bdbe9cd6615e2` |
| `writable/user-presence-review/local-upgrade-results.json` | Object keys: database, imports, existing_tables_checked, existing_records_preserved, old_migrations_preserved, presence_migrations, presence_records, hosting_changed | `4a52f0d6c9656faefb0586dd7532219cebf7bbdbee6c43bb74e170df581071c0` |
| `writable/user-presence-review/mysql-results.json` | Object keys: checks, scratch_database_removed, application_records_changed | `dcb4491ca41d68e94f6be54b97a62efc6bfde4c23ea8f8c4e5bd5e2bd028e8ec` |

[Testing and evidence](../development/Testing-and-Evidence.md) · [Home](../Home.md)
