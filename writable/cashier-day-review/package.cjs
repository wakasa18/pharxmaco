const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const root=process.cwd(), prior=path.join(root,'build/receipt-invoice-fields-2026-10-14'), stage=path.join(root,'build/cashier-workspace-2026-10-15');
function files(dir){return fs.readdirSync(dir,{withFileTypes:true}).flatMap(entry=>entry.isDirectory()?files(path.join(dir,entry.name)):[path.join(dir,entry.name)]);}
const relative=files(prior).map(file=>path.relative(prior,file).replaceAll('\\','/')).filter(file=>file!=='app/Config/ReceiptInvoice.php');
relative.push('app/Config/Routes.php','app/Controllers/Auth.php','app/Views/layouts/sidebar.php','app/Controllers/Admin/SaleCorrection.php',
 'app/Controllers/Admin/ReportsController.php','app/Controllers/StockLogsController.php','app/Libraries/ReportPdf.php',
 'app/Views/exports/pdf_styles.php','app/Views/exports/pdf_header.php','app/Views/exports/pdf_table.php','app/Views/exports/cash_movements_pdf.php',
 'app/Views/admin/reports/index.php','app/Views/admin/reports/reports_pdf.php','app/Views/admin/reports/expiry_report_pdf.php','app/Views/stock_logs/pdf.php',
 'docs/reviews/2026-10-16-pdf-export-update.md',
 'app/Controllers/Cashier/WorkspaceController.php','app/Libraries/CashierShift.php','app/Libraries/CashierDayReport.php','app/Libraries/ProductPromotion.php','app/Libraries/BenefitProductPromotion.php',
 'app/Database/Migrations/2026_10_15_000014_add_cashier_shifts.php','public/assets/css/cashier-workspace.css','public/assets/js/cashier-workspace.js',
 'public/assets/js/datatables-init.js',
 'docs/reviews/2026-10-16-automatic-category-promos.md',
 'docs/reviews/2026-10-16-checkout-fixes.md','database/upgrades/2026-10-16-reconcile-master-stock.sql',
 'docs/reviews/2026-10-16-shift-print-update.md','public/assets/css/shift-print.css','public/assets/js/shift-print.js',
 'database/upgrades/2026-10-16-reconcile-sale41-refunds.sql','database/upgrades/2026-10-16-reconcile-sale41-refunds.md',
 ...files(path.join(root,'app/Views/cashier/workspace')).map(file=>path.relative(root,file).replaceAll('\\','/')),
 'database/upgrades/2026-10-15-cashier-workspace.sql','database/upgrades/2026-10-15-cashier-workspace.md',
 'database/upgrades/2026-10-15-product-catalog.sql','database/upgrades/2026-10-15-product-catalog.md',
 'database/upgrades/2026-10-15-branded-products.sql','database/upgrades/2026-10-15-branded-products.md',
 'database/upgrades/2026-10-15-latest-existing-database.sql','database/upgrades/2026-10-15-latest-existing-database.md',
 'database/upgrades/2026-10-15-demo-users.sql','database/upgrades/2026-10-15-demo-users.md',
 'database/upgrades/2026-10-14-latest-existing-database.sql','database/upgrades/2026-10-14-latest-existing-database.md');
const manifest=[];
for(const file of [...new Set(relative)].sort()){
 const source=path.resolve(root,file), target=path.resolve(stage,file);
 if(!source.startsWith(root+path.sep)||!target.startsWith(stage+path.sep))throw Error('Invalid package path.');
 const data=fs.readFileSync(source);fs.mkdirSync(path.dirname(target),{recursive:true});fs.writeFileSync(target,data);
 manifest.push({file,sha256:crypto.createHash('sha256').update(data).digest('hex')});
}
const example='app/Config/ReceiptInvoice.php.example';fs.mkdirSync(path.join(stage,'app/Config'),{recursive:true});fs.copyFileSync(path.join(root,'app/Config/ReceiptInvoice.php'),path.join(stage,example));
manifest.push({file:example,sha256:crypto.createHash('sha256').update(fs.readFileSync(path.join(stage,example))).digest('hex')});
fs.writeFileSync(path.join(stage,'INSTALL.md'),fs.readFileSync(path.join(root,'database/upgrades/2026-10-15-cashier-workspace.md'),'utf8')+
 '\n\n## Product catalog update\n\nThis package also supports Non-medicine product classification. For the latest supplied hosting export, import database/upgrades/2026-10-15-latest-existing-database.sql. If the cashier upgrade is already installed, use database/upgrades/2026-10-15-product-catalog.sql for product changes only. For twelve additional branded medicines with requested demo stock/prices/expiry, use database/upgrades/2026-10-15-branded-products.sql. The complete latest-existing-database.sql already includes both product seeds. Read the companion guides for the full mapping and demo values.\n');
fs.appendFileSync(path.join(stage,'INSTALL.md'),'\n\n## October 16 checkout and reporting repairs\n\nSee docs/reviews/2026-10-16-checkout-fixes.md. Old-checkout cart preservation, uncertain-response recovery, equal-total quantity correction events and Senior/PWD centavo comparison are included. For derived stock discrepancies only, import database/upgrades/2026-10-16-reconcile-master-stock.sql with checkout/receiving paused. It does not change refund amounts. The separately confirmed sale #41 test-refund correction is in database/upgrades/2026-10-16-reconcile-sale41-refunds.sql; read its companion guide before importing the matching test records.\n');
fs.appendFileSync(path.join(stage,'INSTALL.md'),'\n\n## Updated shift printing\n\nSee docs/reviews/2026-10-16-shift-print-update.md. Print summary keeps reconciliation and signatures together; Print with details includes every original shift movement. No extra SQL import is required.\n');
fs.appendFileSync(path.join(stage,'INSTALL.md'),'\n\n## Updated PDF exports\n\nSee docs/reviews/2026-10-16-pdf-export-update.md. Sales reconciliation, Full/Summary PDF, repeated document identity, inventory units/references and complete stock-log notes are included. No additional SQL import is required.\n');
manifest.push({file:'INSTALL.md',sha256:crypto.createHash('sha256').update(fs.readFileSync(path.join(stage,'INSTALL.md'))).digest('hex')});
fs.writeFileSync(path.join(root,'writable/cashier-day-review/package-manifest.json'),JSON.stringify(manifest,null,2));
console.log(`Staged ${manifest.length} current files; live invoice configuration excluded.`);
