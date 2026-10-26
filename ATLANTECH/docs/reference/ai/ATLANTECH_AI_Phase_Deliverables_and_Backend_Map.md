# ATLANTECH — AI Implementation Phases & Backend Handoffs

> **الغرض من المستند:** مرجع عملي يوضح كل Phase في تنفيذ الـ AI، هدفها، ما يجب تسليمه عند انتهائها، كيف نتحقق من اكتمالها، ومدى ارتباطها بالـ Backend.
>
> **نطاق المستند:** مبني على ملف `ATLANTECH_AI_Implementation_Plan.md`. لا يحل محل `ATLANTECH_AI_Tasks.md` أو `ATLANTECH_AI_Backend_Dependencies.md`؛ أرقام المهام `T*` والقرارات `OD-*` والاعتماديات `BD-*` تظل معرّفة في المستندات الأصلية.
>
> **مهم:** المراحل ليست أسابيع ولا مددًا زمنية. ترتيبها يعكس الاعتماديات الفنية. أي موضوع غير محسوم مذكور بصفته قرارًا مفتوحًا، وليس قرارًا معتمدًا.

---


## 1. ملخص علاقة كل Phase بالـ Backend

| Phase | الموضوع | مستوى الارتباط بالـ Backend | السبب الأساسي |
|---|---|---|---|
| 0 | Foundations & Contracts | **مباشر ومشترك — حرج** | الاتفاق على queue contracts ودورة التشغيل وصلاحيات DB والقيم والحالات وآلية SSE. |
| 1 | Environmental Data & Physics | **مباشر** | يحتاج بيانات إعدادات الميناء والمدخلات اليدوية وقراءة الحالة وإشعار الواجهة. |
| 2 | Feasibility Windows & Demand | **مباشر — حرج** | الـ Backend يعرض النوافذ ويعتمدها ويعيد التحقق منها ويبدأ Part 2. |
| 3 | EDI / BAPLIE Parser | **مباشر** | رفع الملف وربطه بالطلب وإزالة التكرار وqueue ونتيجة parsing وSSE. |
| 4 | Core Recommendation Engine | **مباشر — حرج** | يحتاج بيانات الموارد والحالة الحية وعقد التوصية وتأكيد الخطة والـ overrides. |
| 5 | Re-planning & Terminal Recommendations | **مباشر** | يحتاج master data للمحطة وتدفقات التوصيات والتأكيد والتصدير إلى TOS. |
| 6 | Evaluation, Drift, Registry & Bias Correction | **مباشر جزئيًا إلى مشترك** | يحتاج بيانات نتائج موثوقة وحقول ثقة ومكانًا لسجل الموديلات وإظهار تنبيهات drift. |
| 7 | LightGBM & Guarded Retraining | **تكامل محدود نسبيًا، لكنه يحتاج دعمًا تشغيليًا** | الموديلات تُحمّل داخل مكونات الـ AI، لكن يلزم دعم التخزين والصلاحيات وإظهار candidates عند الاقتضاء. |
| 8 | End-to-End Verification | **مشترك — حرج** | لا يمكن إثبات سير العمل الكامل من دون Backend متكامل أو stub مطابق للعقود، ثم اختبار على النظام الحقيقي. |

> **كيف نقرأ مستوى الارتباط؟** لا يعني «ارتباط محدود» أن المرحلة لا تحتاج اختبارات تكامل؛ بل يعني أن الخطة لا تحدد بالضرورة واجهة Backend جديدة لكل جزء منها. المرجع الحاسم هو الـ contract المعتمد والـ Backend Dependency المرتبط بكل Task.

---

## 2. تفاصيل كل Phase

## Phase 0 — Foundations and Contracts

**نوع المرحلة:** تأسيسية ومشتركة مع الـ Backend.  
**المهام المرتبطة:** `T0.1–T0.9`  
**الهدف:** حسم العقود والقرارات التي يحتاجها الـ AI والـ Backend قبل بناء الوظائف الفعلية.

### ما الذي سيتم تنفيذه؟

- إنشاء هيكل مشروعات الـ AI والمكتبة المشتركة.
- تحديد شكل الـ job payload الذي يرسله Laravel إلى كل queue، والعقد الخاص بنتيجة التنفيذ.
- تعريف دورة حياة التشغيل وحالات النجاح والفشل وإعادة المحاولة والتعامل مع الطلب غير المؤهل.
- حسم طريقة اكتشاف Laravel لاكتمال الـ Python worker حتى يستطيع تحديث الواجهة عبر SSE.
- تحديد مستخدم قاعدة البيانات الخاص بالـ AI وصلاحيات القراءة والكتابة، مع حماية `audit_log`.
- توحيد أسماء الحقول والحالات والقيم؛ وتوثيق أي تغييرات SQL مطلوبة بعد مراجعة `PortSync.sql`.
- تجهيز آلية مشتركة لقراءة حدود السلامة، وTest Harness يتضمن بيانات/سيناريوهات اختبار وLaravel-stub مطابقًا للعقود.
- توثيق سياسة استخدام البيانات للتدريب، أو حسم تأجيلها مع بقاء Phase 7 gated.

### التسليمات المطلوبة (Deliverables)

1. AI repository skeleton والمكتبة المشتركة.
2. Job envelope وrun-lifecycle contract موثقان ومحددا الإصدار.
3. قرار مكتوب حول completion signalling بين Python وLaravel وSSE.
4. قائمة صلاحيات قاعدة البيانات وقواعد الكتابة والقراءة.
5. Vocabulary/constants موحدة وقائمة SQL changes متفق عليها.
6. Shared safety-limit loader مع اختبارات.
7. Test Harness يحتوي على سيناريوهات تشغيل وLaravel-stub.
8. سجل/وثيقة سياسة بيانات التدريب والقرارات المرتبطة بها.

### كيف نعرف أنها انتهت؟

- تجربة job على كل queue واختبار إعادة المحاولة عند إدخال فشل متعمد.
- التحقق من رفض الطلب غير المؤهل للتنفيذ.
- إثبات أن فشل الكتابة متعددة الجداول يؤدي إلى rollback كامل.
- إثبات أن مستخدم AI لا يستطيع تعديل أو حذف سجلات `audit_log`.
- اختبار أن Safety-limit loader يختار القيمة الأكثر تشددًا عند تداخل النطاقات.
- توثيق أسماء الحالات والحقول ومعانيها مع رقم إصدار للعقد.
- تحديد القرارات المطلوبة مثل `OD-5` و`OD-6` و`OD-13`، وحسم `OD-4` أو تأجيله بصورة صريحة مع إبقاء Phase 7 مشروطة.

### علاقتها بالـ Backend

**ارتباط مباشر وحاسم.** لا يستطيع الـ Backend تثبيت كود إرسال الـ jobs، أو آلية تحريك SSE، أو migrations النهائية قبل الاتفاق على العقود.

**تسليمات للـ Backend:**

- `BD-1`: Queue payload contract.
- `BD-2`: Completion signalling.
- `BD-3`: DB user and grants.
- `BD-4`: SQL enum/status changes.

**بوابة الخروج:** مراجعة مشتركة للـ contracts وتسليم قائمة تغييرات SQL والقرارات المحددة للفريق المسؤول عن الـ Backend.

---

## Phase 1 — Part 1: Environmental Data and Physics

**نوع المرحلة:** بناء مدخلات Part 1 البيئية.  
**المهام المرتبطة:** `T1.1–T1.8`  
**الهدف:** جلب بيانات الطقس ومستوى البحر، وحساب المد والجزر داخليًا، واشتقاق الـ surge، وتخزين النتائج مع سلوك واضح للبيانات القديمة أو تعطل المصادر.

### ما الذي سيتم تنفيذه؟

- بناء Part 1 worker والـ scheduler الخاص بجلب البيانات الدوري.
- إنشاء provider adapter يدعم المصدر الأساسي والاحتياطي وفق القرار المعتمد.
- تخزين البيانات الخام وتمييزها عن القيم المحوّلة.
- تنفيذ tide prediction داخليًا وتخزين النتائج.
- حساب surge وفق العلاقة الموثقة: `Surge = Sea Level - Tide`، مع توحيد الوحدات.
- تحديد قواعد قبول/رفض البيانات حسب عمرها ومدى تغطيتها للفترة المطلوبة.
- دعم إدخال البيانات يدويًا مع تسجيل مصدرها والمستخدم الذي أدخلها وفق عقد الـ Backend.
- الحفاظ على البيانات السابقة إذا فشل الحساب بدل إفساد نتائج سليمة موجودة.

### التسليمات المطلوبة

1. Part 1 Python worker.
2. Scheduler لجلب البيانات دوريًا.
3. Provider adapter مع primary/secondary fallback.
4. تخزين `forecast_run` و`environment_forecast_point` وفق الـ schema المعتمد.
5. Tide module يكتب في `tide_prediction_run` و`tide_prediction_point` وفق التسمية النهائية في قاعدة البيانات.
6. Surge calculation function.
7. قواعد صلاحية البيانات وسلوك `ENV_DATA_UNAVAILABLE`.
8. مسار البيانات اليدوية وتسجيل المصدر.
9. اختبارات فشل المصادر والبيانات القديمة والنتائج المرجعية للمد والجزر.

### معايير الاكتمال

- نجاح المصدر الأساسي، وفشل الأساسي مع استخدام الاحتياطي، وفشل الاثنين مع تسجيل `data_stale` والاحتفاظ بالبيانات السابقة حسب السياسة.
- القيم الخام المخزنة تطابق قيم المصدر، والقيم المحوّلة محفوظة بصورة منفصلة.
- مقارنة tide output بسلسلة مرجعية، مع الاتفاق على tolerance ضمن `OD-14`.
- اختبار الساعات الناقصة وفشل الحساب من دون استبدال التوقعات السابقة.
- البيانات الأقدم من 48 ساعة أو التي لا تغطي أفق الطلب تنتج `ENV_DATA_UNAVAILABLE`، مع اختبار الحالات الحدية.
- رفض البيانات اليدوية الناقصة أو غير المغطية للفترة المطلوبة، وتسجيل `MANUAL` عند قبولها.
- نجاح سيناريو stub من الموافقة المطلوبة إلى `DONE` أو `ENV_DATA_UNAVAILABLE`، وتمكن الـ Backend من قراءة الحالة وإطلاق SSE حسب العقد المتفق عليه.

### علاقتها بالـ Backend

**ارتباط مباشر.** الـ AI يحتاج إعدادات وبيانات الميناء، والـ Backend يحتاج تخزين البيانات اليدوية ومعرفة حالة التشغيل وعرضها للمستخدمين.

**تسليمات/اعتماديات للـ Backend:**

- `BD-5`: إحداثيات الميناء ومجموعات constituents وchart datum وUKC parameters، مع مسؤولية الإعداد والتحرير كما تنص الوثائق.
- `BD-6`: اتفاق على صيغة ووحدات surge المستخدمة في عرض الظروف البيئية.
- `BD-7`: حفظ بيانات الإدخال اليدوي والمستخدم الذي أدخلها، وآلية enqueue لإعادة الحساب.
- `BD-2`: آلية إشارة اكتمال التشغيل.

**بوابة الخروج:** بيانات بيئية مخزنة ومختبرة، وحالات الفشل واضحة، وتكامل الحالة وSSE متحقق وفق العقد.

---

## Phase 2 — Part 1: Feasibility, Windows and Demand

**نوع المرحلة:** منطق تحديد فترات الدخول والقيود.  
**المهام المرتبطة:** `T2.1–T2.9`  
**الهدف:** تحويل البيانات البيئية وخصائص السفينة إلى `feasible_windows` و`blocked_periods` ومعلومات الازدحام وأسباب المنع.

### ما الذي سيتم تنفيذه؟

- تنفيذ threshold rules للحدود البيئية واختبار حالات النجاح والفشل والحالات الحدية.
- تنفيذ حساب UKC واستخدام Barrass squat method حسب الخطة.
- تنفيذ uncertainty margin وفق الطريقة التي يتم حسمها.
- بناء windows وتسجيل الفترات المحجوبة والعامل الذي قيّدها.
- احتساب congestion score وتجميع M3 في مرحلته الأولى حسب المهام المعتمدة.
- دعم إعادة الحساب وإعادة فتح النتائج، مع منع خلط بيانات طلب ببيانات طلب آخر.
- إضافة hook لقراءة bias correction؛ ويظل هوية/no-op إلى أن تتوفر correction من Part 3.
- تنفيذ اختبارات Part 1 وintegration check الأول.

### التسليمات المطلوبة

1. Threshold rules (A4).
2. UKC/Barrass squat calculations (A5).
3. Uncertainty-margin implementation (A6) بعد حسم طريقتها.
4. Window builder وكتابة النتائج وفق العقد.
5. منطق recompute/reopen.
6. Bias-correction read hook، ويعمل كهوية عند عدم وجود correction.
7. M3 Phase 1 aggregation وفق الـ task breakdown.
8. Test suite تتضمن golden scenarios.
9. تكامل أول مع Laravel أو stub مطابق للعقود.
10. عقد Part 1 output محدد الإصدار ومقبول من فريق الـ Backend.

### شكل المخرج الذي يجب الاتفاق عليه

بحسب الخطة، يتضمن عقد Part 1:

- `feasible_windows`: بداية ونهاية كل window وconfidence.
- `blocked_periods`: بداية ونهاية الفترة والعامل المقيّد.
- `peak_congestion_score`.
- `data_versions`: إصدارات/تشغيلات الطقس والمد والجزر المستخدمة.
- توضيح limiting factor عند عدم وجود أي window ممكنة.

### معايير الاكتمال

- لكل حد بيئي اختبارات pass/fail/edge.
- اختبارات UKC/squat تطابق حالات مرجعية محسوبة يدويًا.
- لا تُخفف الـ hard limits بسبب uncertainty margin أو bias correction.
- إعادة الحساب تستبدل الاقتراح السابق في transaction واحدة، ولا تعيد استخدام بيانات طلب آخر.
- نجاح السيناريوهات: safe، وكل عامل منع، وtidal window، وعدم وجود بيانات، وبيانات stale، وبيانات يدوية.
- نجاح integration check: طلب يمر بمسار الموافقة المطلوب في Laravel، وينتج النتائج وSSE وwindow يمكن تأكيدها.

### علاقتها بالـ Backend

**ارتباط مباشر وحاسم.** هنا يبدأ الـ Backend في استهلاك مخرجات Part 1 وعرضها واعتمادها، ثم تشغيل Part 2 بعد تأكيد window صالحة.

**تسليمات/اعتماديات للـ Backend:**

- `BD-8`: خصائص السفينة اللازمة، ومنها draft وblock coefficient، على `berthing_request` وفق العقد النهائي.
- `BD-9`: عملية تأكيد window؛ إعادة التحقق من أحدث نتيجة ومن صلاحيتها، وإعادة الفتح/recompute، ثم enqueue لـ Part 2 عند التأكيد.
- `BD-1` و`BD-2`: عقود queue وإشعار اكتمال التشغيل.

**قرارات يجب حسمها:** `OD-3` لمعنى confidence، و`OD-9` لطريقة الهامش، و`OD-10` لمالك ومسار إعادة التحقق عند تأكيد window.

**بوابة الخروج:** عقد Part 1 معتمد ومحدد الإصدار، القرارات اللازمة محسومة، اختبارات الفيزياء والـ golden scenarios ناجحة، وintegration check الأول ناجح.

---

## Phase 3 — EDI Parser / BAPLIE Processing

**نوع المرحلة:** مكوّن مستقل يمكن تنفيذه بالتوازي مع Phase 1 وPhase 2.  
**المهام المرتبطة:** `T3.1–T3.6`  
**الهدف:** قراءة ملفات BAPLIE والتحقق منها بصورة deterministic، مع اعتماد كل التغييرات التشغيلية كاملة أو عدم اعتماد أي منها.

### ما الذي سيتم تنفيذه؟

- EDI Parser worker على `queue:edi`.
- Parser وفق D.17A / SMDG 2019 حسب ما ورد في الخطة.
- قواعد validation ورسائل diagnostics قابلة للاستخدام من الموظفين.
- حفظ ذري (atomic) للتغييرات التشغيلية.
- آلية correction/re-validation تستخدم نفس مجموعة القواعد بعد حسم مكان تشغيلها.
- Fixtures لملفات صحيحة ومعطوبة وقياس أزمنة المعالجة.

### التسليمات المطلوبة

1. Parser worker.
2. Parsing/validation implementation وفق المعيار المحدد.
3. Diagnostics لكل حقل أو مشكلة قابلة للتشخيص.
4. Staged payload للملف الذي به أخطاء، من دون تطبيق تغيير تشغيلي جزئي.
5. Atomic commit routine للملف الصحيح.
6. Fixture set تغطي ملفات سليمة، ناقصة، معطوبة، وحالات unknown segments.
7. نتائج قياس parsing performance وفق NFR المعني بعد تأكيد تعريفه.
8. قرار موثق بشأن مسار إعادة التحقق بعد التصحيح (`OD-11`).
9. تكامل upload → parse → SSE مع الـ Backend.

### معايير الاكتمال

- الملف الصحيح يحدث البيانات المطلوبة في transaction واحدة: الحاويات/manifest، حالة الملف السابق `REPLACED` إن انطبق، والملف الجديد `ACTIVE` وحالة parsing `COMPLETED` وفق العقد المعتمد.
- الملف غير الصالح لا يترك تغييرات تشغيلية جزئية؛ تُحفظ diagnostics والـ staged payload وتكون حالته `HAS_ERRORS` وفق الخطة.
- اختلاف IMO عن السفينة المرتبطة بالطلب خطأ مانع.
- unknown segments تنتج warnings حيث تسمح القواعد، مع استمرار تحميل الأجزاء الصالحة وفق الخطة.
- مدخل متطابق يعطي حكمًا متطابقًا في parser الأصلي ومسار التصحيح.
- اختبار atomicity بحقن فشل متعمد، ثم نجاح تدفق الرفع والتحليل وإشعار SSE.

### علاقتها بالـ Backend

**ارتباط مباشر.** الـ Backend يملك تدفق رفع الملف وربطه بالطلب وإزالة التكرار وإنشاء job وإظهار الحالة؛ والـ Parser يملك parsing والتحقق والمعالجة المحددة له.

**تسليمات/اعتماديات للـ Backend:**

- `BD-10`: upload، hash/duplicate check، file volume mount، linking/auto-match، enqueue.
- `BD-4`: قيم وحالات BAPLIE المستعملة في التخزين.
- `OD-11`: أين توجد بيانات التصحيح وأين ينفذ re-validation.

**بوابة الخروج:** الاختبارات تشمل malformed files، atomicity مثبتة، قرار `OD-11` محسوم، وتدفق الرفع إلى parsing ثم SSE يعمل.

---

## Phase 4 — Part 2: Core Recommendation Engine

**نوع المرحلة:** محرك التوصيات وتخصيص الموارد.  
**المهام المرتبطة:** `T4.1–T4.14`  
**الهدف:** إنتاج توصيات مرتبة وقابلة للتفسير للأرصفة والـ tugs والـ pilots انطلاقًا من window مؤكدة وبيانات تشغيلية حديثة.

### ما الذي سيتم تنفيذه؟

- Part 2 worker ومعالجة `solver_run`.
- تحميل live state والـ master data اللازمة.
- Hard-constraint filter يستبعد الخيارات المخالفة قبل اعتماد التوصيات.
- تنفيذ M2 Phase 1 وM4 rule-based score حسب الـ task breakdown.
- حساب عدد الـ tugs وفق القواعد المعتمدة.
- CP-SAT optimization model مع تسجيل `objective_term`.
- ترتيب الخيارات وتوثيق مبرراتها عبر rationale templates ذات إصدارات.
- معالجة حالات solver success، والفشل التقني الذي يسمح بـ FIFO fallback، وعدم وجود حل `NO_FEASIBLE_PLAN`.
- الحفظ transactionally والتحقق من الـ overrides.
- اختبارات وظيفية وقياس أداء وintegration check الثاني.

### التسليمات المطلوبة

1. Part 2 worker و`solver_run` handling.
2. Live-state loader.
3. Hard constraint filter واختباراته.
4. M2 Phase 1 وM4 rule-based scoring وفق تعريفاتهما المعتمدة.
5. Tug-count function.
6. CP-SAT model وتسجيل `objective_term`.
7. Ranking implementation.
8. Handling paths موثقة للنجاح وFIFO fallback و`NO_FEASIBLE_PLAN`.
9. Transactional persistence للتوصيات والبيانات المرتبطة بها.
10. Rationale templates versioned.
11. Override validation function بعد حسم آلية الاستدعاء.
12. Test suite وgolden contention scenarios.
13. Integration check الثاني: window confirm → recommendations → SSE → review/override → plan confirm.
14. نتائج قياس زمن الحل والاستجابة وفق تعريفات الأداء المعتمدة.

### ما الذي يجب أن تحتويه التوصية؟

وفق الخطة، الخيارات المرتبة تشمل على الأقل الحقول التالية بحسب العقد النهائي:

- `rank`
- `berth_id`
- start/end
- عدد الـ tugs
- `pilot_id`
- service-time P50 وP90
- `delay_risk`
- `rationale_text`
- recommendation version من نوع `GENERATED` ومصدر التوصية (AI أو FIFO) وفق التسمية المعتمدة.

### معايير الاكتمال

- نتائج النجاح والفشل التقني وعدم وجود حل تسجل بالشكل الموثق.
- FIFO يمر بنفس hard filter، ويُعلّم بوصفه fallback؛ ولا ينتج fallback عندما تكون الحالة infeasible.
- أي candidate يخالف hard constraint يُستبعد في الاختبارات.
- سيناريو تنافس سفن على pilot أو tug في فترات متداخلة يُعالج بالـ joint solve حسب الاختبار المرجعي.
- تُقاس المدد وفق NFRs بعد حسم تعريف القياس في `OD-17`.
- لكل خيار rationale ناتج عن template محدد الإصدار.
- اختبار التكامل يشمل حالة `409` إذا أصبح المورد غير متاح بين مراجعة الخطة وتأكيدها.

### علاقتها بالـ Backend

**ارتباط مباشر وحاسم.** الـ Backend هو الذي يوفر الحالة التشغيلية، ويبدأ الـ job، ويعرض التوصيات، ويتعامل مع المراجعة والتأكيد والـ override وتغير توافر الموارد.

**تسليمات/اعتماديات للـ Backend:**

- `BD-11`: جداول pilot/tug، الورديات، حدود الرتب والحجوزات في `plan_component`.
- `BD-12`: بيانات `terminal_productivity_profile`.
- `BD-13`: transaction تأكيد الخطة، إعادة التحقق والـ rollback، تخزين override وإدارة الإصدارات.
- `OD-12`: آلية override validation.
- `OD-15`: نطاق الحل والتزامن.
- `OD-16`: تعريف P90 ضمن زمن الخدمة في Phase 1.
- `OD-1` و`OD-2` و`OD-3`: قرارات عدد الـ tugs والأثر المالي ومعنى confidence/delay-risk.

**بوابة الخروج:** عقد التوصية محدد الإصدار ومقبول، قياسات الأداء موثقة، الاختبارات المرجعية وintegration check الثاني ناجحة، والقرارات التي تمنع التنفيذ حُسمت.

---

## Phase 5 — Part 2: Re-planning and Terminal Recommendations

**نوع المرحلة:** توسعة محرك التوصيات بعد اكتمال الأساس.  
**المهام المرتبطة:** `T5.1–T5.5`  
**الهدف:** دعم إعادة التخطيط بعد تغييرات التشغيل، ثم إخراج توصيات المحطة، بدءًا بالـ Container Terminal، مع إبقاء NCT مشروطة بالنطاق المعتمد.

### ما الذي سيتم تنفيذه؟

- Re-planning باستخدام `REPLAN` و`RESOURCE_CHANGE` على rolling horizon.
- معالجة التغيرات من دون تعديل خطة مؤكدة بطريقة تتجاوز دورة الاعتماد.
- الحفاظ على المكونات التي بدأت بالفعل (`STARTED`) عند تأكيد إعادة التخطيط، وإرجاع المكونات المتغيرة إلى مسار إعادة التخصيص وفق العقد.
- Container Terminal yard placement مع قيود yard structure وreefer وOOG وDG class وفق المتطلبات الموثقة.
- CT timing recommendation.
- NCT storage allocation إذا تقرر إدخاله في نطاق الـ MVP/التسليم.
- تكامل تدفق قبول التوصية أو تعديلها أو رفضها، وإخراج TOS-ready payload وتسجيل التصدير.

### التسليمات المطلوبة

1. Re-planning logic لـ `REPLAN` و`RESOURCE_CHANGE`.
2. CT yard-placement recommendations قابلة للاستخدام في TOS.
3. CT timing recommendation.
4. NCT storage allocation **كجزء مشروط** حتى يُحسم النطاق.
5. Rationale وconfidence لكل توصية وفق العقد المعتمد.
6. آلية تمييز اقتراح إعادة التخطيط الناتج عن التأخير عن الإصدار العادي.
7. Integration check الثالث، ويشمل plan confirmed → yard job → accept/adjust/reject → TOS-ready payload → export recorded، بالإضافة إلى تأكيد re-plan.

### معايير الاكتمال

- تغيّر مورد يعيد إنشاء التوصيات المعلقة فقط؛ لا يغيّر الخطة المؤكدة تلقائيًا.
- اقتراح التأخير يمكن تمييزه عن النسخة العادية، وتأكيده يحافظ على `STARTED` ويعيد العناصر المتغيرة إلى assignment وفق العقد.
- اقتراحات yard تراعي قيود الهيكل والحاويات المبردة وOOG وDG، وكل تعديل يعاد التحقق منه.
- التوصيات متاحة فقط للـ terminal المصرح له بها.
- تدفق التوصية إلى TOS وتسجيل التصدير يمر بنجاح.
- NCT إما منفذ أو مؤجل رسميًا وفق قرار `OD-18`.

### علاقتها بالـ Backend

**ارتباط مباشر.** يحتاج الـ Backend إلى تخزين وإدارة بيانات الساحة، وتقديم تدفقات التوصيات وحالاتها، وعرض TOS-ready output، وتسجيل التصدير، وتنفيذ transaction تأكيد إعادة التخطيط.

**تسليمات/اعتماديات للـ Backend:**

- `BD-14`: yard master data، endpoints وحالات yard recommendations، عرض TOS payload وتسجيل export.
- `BD-13`: معاملة تأكيد re-plan.
- `OD-13`: marker أو طريقة تمييز إصدار اقتراح التأخير.
- `BD-4`: القيم والحالات المعتمدة.

**بوابة الخروج:** تدفق CT متحقق، عقد re-plan محدد الإصدار، وقرار نطاق NCT موثق.

---

## Phase 6 — Part 3: Evaluation, Drift, Registry and Bias Correction

**نوع المرحلة:** تقييم ومراقبة وملفات تشغيل الموديلات، من دون retraining في هذه المرحلة.  
**المهام المرتبطة:** `T6.1–T6.8`  
**الهدف:** قياس أداء النظام من بيانات موثوقة، ومراقبة الـ drift، وإدارة سجل الموديلات محليًا، وتطبيق bias correction إذا كان ضمن النطاق النهائي المعتمد.

### ما الذي سيتم تنفيذه؟

- Part 3 worker والـ scheduler المحلي.
- تحميل outcome data موثوقة واستبعاد السجلات غير المناسبة وفق flags وسياسة الثقة.
- حساب metrics وحفظها في `evaluation_metric`.
- مراقبة drift وحفظ القياسات والتنبيه في `drift_measurement` والـ alert rows حسب التصميم.
- إعداد model registry محليًا مع مفاتيح `ml_model` المطلوبة.
- تحليل الـ overrides في تقرير لا يطلق تغييرات تشغيلية تلقائيًا.
- Bias correction module بإصدار `FORECAST_BIAS_CORRECTION` إذا أكّد قرار النطاق أنه مطلوب هنا.
- Guard tests تمنع الكتابة إلى safety-limit tables وتتحقق من بقاء artifacts على خادم الميناء.

### التسليمات المطلوبة

1. Part 3 worker وscheduler.
2. Trusted-data loader وقواعد استبعاد السجلات غير الموثوقة.
3. Evaluator يكتب `evaluation_metric`.
4. Drift monitor يكتب `drift_measurement` وحالة التنبيه اللازمة.
5. Model registry محلي ومفاتيح `ml_model` المحددة.
6. Bias-correction module مؤرّخ/محدد الإصدار، **بشرط حسم دخوله في النطاق**.
7. Override analysis report.
8. Guard tests للكتابة إلى جداول السلامة وتصدير الـ artifacts والاتصالات الخارجية.
9. اختبارات قابلة لإعادة إنتاج metrics من البيانات المخزنة.

### معايير الاكتمال

- لا تدخل السجلات المشكوك في clock-skew أو المصححة يدويًا إلى datasets التي تتطلب بيانات موثوقة وفق الخطة.
- يمكن إعادة إنتاج MAE وoverride rate وwait time وPart 1 window accuracy من البيانات المخزنة.
- drift المحقون يرفع alert row، والحالة السليمة تسجل على أنها healthy.
- إذا كان bias correction ضمن النطاق، يستهلكه Part 1 ويُسجل الإصدار من دون تغيير raw provider data.
- تقرير الـ overrides لا يطلق تغييرات تشغيلية تلقائية.
- اختبارات الحماية تثبت عدم تعديل safety-limit tables، وعدم إخراج artifacts خارج الخادم، وعدم استخدام outbound network خارج مزودي الطقس وفق الخطة.

### علاقتها بالـ Backend

**ارتباط مباشر جزئيًا.** لا تنشئ كل وظيفة مراقبة بالضرورة endpoint جديدًا، لكن النتائج تحتاج إلى حقول ثقة من الـ Backend، ومكان محلي للـ registry، وطريقة توصيل drift alerts إلى Port Admin عبر SSE.

**تسليمات/اعتماديات للـ Backend:**

- `BD-15`: حفظ trust flags وclock-skew flags على السجلات التي ستُستخدم للتقييم أو التدريب.
- `BD-16`: إعداد volume mount ومكان registry وصلاحياته.
- `BD-17`: إيصال drift alerts إلى Port Admin عبر SSE من الصفوف التي يكتبها Part 3.

### نقطة نطاق يجب عدم افتراضها

في خطة التنفيذ الأصلية يوجد عدم اتساق يحتاج قرارًا: جزء يذكر أن MVP الخاص بـ Part 3 يقتصر على evaluation وdrift، بينما قائمة مخرجات هذه المرحلة ومعايير خروجها تتضمن bias correction. يجب الرجوع إلى FRD والقرار `OD-18` لحسم ما إذا كان bias correction ضمن الـ MVP أم مؤجلًا. لا تعتبر هذه النقطة محسومة من هذا الملف.

**بوابة الخروج:** نجاح evaluation وdrift على Fixtures والبيانات الحقيقية المتاحة، واجتياز اختبارات الحماية، واختبار استهلاك bias correction إذا تقرر إدراجه في النطاق.

---

## Phase 7 — LightGBM Models and Guarded Retraining (Gated)

**نوع المرحلة:** مشروطة بجاهزية البيانات وقرار إدراجها في النطاق.  
**المهام المرتبطة:** `T7.1–T7.8`  
**الهدف:** استبدال formula/rule baseline بموديلات LightGBM فقط عندما تثبت البيانات الموثوقة أن الموديل يحسن الأداء، مع ترقية قابلة للتراجع.

### ما الذي سيتم تنفيذه؟

- إنشاء dataset builders مرتبة زمنيًا وتستخدم البيانات التي اجتازت شروط الثقة فقط.
- بناء وتقييم M2 quantile model وM4 classifier وM3 regressor حسب التعريفات الفعلية في وثائق المشروع والـ tasks.
- مقارنة كل موديل بالـ formula/rule baseline على بيانات اختبار زمنية لم يستخدمها التدريب.
- تقديم Champion/Challenger promotion process يسجل القرار وإصدارات الموديلات.
- الاحتفاظ بالنسخة السابقة ودعم rollback.
- استخدام formula/rule fallback إذا كان الموديل مفقودًا أو تالفًا أو أعطى output غير صالح.
- تشغيل guarded retraining فقط وفق بوابات الجاهزية والسياسة المعتمدة.

### التسليمات المطلوبة

1. Dataset builders لكل موديل، مع فلاتر الثقة والترتيب الزمني.
2. M2 quantile model، وM4 classifier، وM3 regressor وفق task definitions.
3. نتائج مقارنة كل موديل بالـ baseline.
4. Model-serving path مع fallback.
5. Retraining/promotion pipeline مع Champion/Challenger decision وrollback.
6. تسجيل قرارات `PROMOTED` أو `KEPT_CHAMPION` أو `FAILED` مع إصدارات الموديلات.
7. مخرجات flagged candidates وفق سياسة النظام.
8. تحقق integration يثبت أن النموذج بعد الترقية لا يخرق عقد Part 2 أو hard constraints.

### معايير الاكتمال

- الموديل لا يُرقّى إلا إذا حقق معيار التفوق المعتمد في `OD-14` مقارنة بالـ baseline.
- الـ challenger لا يُرقّى إذا كان أسوأ من الـ champion وفق MAE أو override rate حسب قاعدة القرار المتفق عليها.
- يمكن استعادة النسخة السابقة بنجاح.
- فشل model load أو invalid output يرجع إلى formula/rule path من دون إسقاط العملية.
- التدريب والموديلات يظلان على خادم الميناء.
- لكل موديل قرار نهائي موثق: إما promoted ومختبر end-to-end، أو baseline retained مع سبب مسجل.

### علاقتها بالـ Backend

**التكامل المباشر أقل من Phase 2 أو 4، لكنه ليس معدومًا.** الخطة لا تتطلب request/response interface جديدة لمجرد تحميل الموديلات؛ إذ تُحمّل داخل مكونات الـ AI. لكنها تحتاج دعم التخزين والصلاحيات، كما أن عرض flagged candidates لمدير الميناء له اعتماد Backend وفق التصميم.

**تسليمات/اعتماديات للـ Backend:**

- `BD-16`: model volume والـ permissions.
- `BD-17`: إظهار flagged candidates/التنبيهات المطلوبة لمدير الميناء وفق العقد.

**بوابة الخروج:** لكل موديل قرار موثق ومدعوم بالتقييم، مع اختبار التكامل أو تسجيل سبب بقاء baseline وعدم الترقية.

---

## Phase 8 — End-to-End Verification and Release Readiness

**نوع المرحلة:** تحقق تكاملي نهائي ومشترك مع الـ Backend.  
**المهام المرتبطة:** `T8.1–T8.5`  
**الهدف:** إثبات أن منظومة الـ AI تعمل داخل تدفق ATLANTECH الحقيقي، وأن حالات الفشل والتشغيل يمكن تفسيرها وتشغيلها وتسليمها.

### ما الذي سيتم تنفيذه؟

- اختبار رحلة العمل الكاملة من الطلب والموافقة المطلوبة، مرورًا بـ Part 1 والـ window confirmation وPart 2 والمراجعة وتأكيد الخطة، وحتى dispatch وfield actuals وdeviation وre-plan وPart 3 evaluation.
- حقن حالات فشل مثل worker متوقف، job غير صالح، فشل DB أثناء الكتابة، تعطل provider، solver timeout، وinvalid model output.
- اختبار عدم ترك partial writes عند الفشل.
- اختبار الأداء والسعة وفق NFRs والتعريفات التي تم حسمها.
- التحقق من عزل البيانات وصلاحيات التشغيل، وعدم تغيير safety limits أو إخراج artifacts من الخادم.
- تجهيز حزمة تسليم تشغيلية لفريق Backend.

### التسليمات المطلوبة

1. End-to-end test suite.
2. Failure-injection report يوضح السيناريو والنتيجة المتوقعة والفعلية.
3. Capacity/latency measurement report.
4. Isolation/security verification results.
5. Runbook للتشغيل والتعامل مع الحالات المعروفة.
6. نسخ العقود المعتمدة وإصداراتها.
7. Model/version cards والقيود المعروفة وأسباب بقاء أي baseline.
8. Handoff pack جاهز لفريق Backend والتشغيل.

### معايير الاكتمال

- تدفق العمل الكامل ينجح وفق السيناريو المحدد في الخطة.
- جميع حالات الفشل المختبرة تنتج السلوك الموثق، وlog مناسبًا، ولا تترك كتابات جزئية غير صالحة.
- تسجيل نتائج الأداء والسعة ضد NFR-PERF-1 وNFR-ENG-1 وNFR-CAP-2، بعد تثبيت طريقة القياس في `OD-17`.
- لا تتغير حدود السلامة ولا تخرج model artifacts من الخادم.
- كل معيار إما مستوفى أو يوجد استثناء صريح ومسجل؛ لا يتم إخفاء المعايير غير المستوفاة.

### علاقتها بالـ Backend

**ارتباط مشترك وحاسم.** لا يمكن إثبات الـ end-to-end الحقيقي دون تنفيذ تدفقات الـ Backend اللازمة. يمكن استخدام Laravel-stub مطابق للعقود أثناء التطوير، لكن يجب استبدال هذا الاختبار بتكامل مع Laravel الحقيقي قبل اعتبار التكامل النهائي مكتملًا.

**تسليم نهائي للـ Backend:** Runbook، نسخ العقود، Model/version cards، نتائج الأداء، حالات الفشل المعروفة، والقيود أو الاستثناءات المسجلة.

**بوابة الخروج:** اكتمال معايير القبول أو تسجيل استثناء واضح ومعتمد لكل بند غير مكتمل.

---

## 3. طبقات التحقق: ما الذي يثبته كل اختبار؟

| طبقة الاختبار | ما الذي تثبته؟ | أمثلة من الخطة |
|---|---|---|
| Component / Model Evaluation | صحة الفيزياء والقواعد والمعادلات والموديلات كلٌ على حدة | `T2.1–T2.3`، `T4.3–T4.8`، `T7.3–T7.5` |
| Interface / Contract Validation | أن المدخلات والمخرجات والحالات تطابق العقود المتفق عليها | `T0.2–T0.5`، `T2.4`، `T4.10` |
| Integration Testing | أن queue → worker → MySQL → SSE ومسارات الموافقة البشرية تعمل مع Backend | `T2.9`، `T4.14`، `T5.5` |
| End-to-End / Failure Testing | أن دورة العمل الكاملة وسلوك الفشل متوافقان مع النظام | `T8.1–T8.4` |

لا يُعد نجاح اختبار الموديل وحده دليلًا على نجاح التكامل. وبالمثل، نجاح queue لا يثبت أن قرار السلامة أو توصية التخصيص صحيحة؛ كل طبقة لها غرض مختلف.

---


## 4. ما الذي يجب أن يتسلمه فريق الـ Backend من فريق الـ AI؟

بحسب المراحل، التسليم ليس ملفات Python فقط. يجب أن تكون هناك حزمة واضحة يمكن البناء عليها في خطة الـ Backend:

1. **Versioned contracts:** Queue payloads، run lifecycle، status vocabulary، AI input/output schemas.
2. **Persistence requirements:** ما الذي يقرأه ويكتبه كل worker، وأي SQL changes أو fields يحتاجها، بعد اعتمادها على ضوء `PortSync.sql`.
3. **Data/master-data requirements:** إعدادات الميناء، خصائص السفن، توافر الـ pilots/tugs، terminal productivity، وyard master data حسب المرحلة.
4. **Completion and notification flow:** كيف يعرف Laravel أن العملية انتهت، وما الذي يؤدي إلى SSE أو ظهور تنبيه.
5. **Human-gate contracts:** تأكيد window، مراجعة التوصية، override، تأكيد الخطة، re-planning، والتعامل مع تغير توافر الموارد.
6. **Failure behavior:** النتيجة المتوقعة عند فشل worker/provider/solver أو وصول input غير صالح.
7. **Testing evidence:** نتائج contract tests وintegration checks وend-to-end/failure tests.
8. **Operational handoff:** Runbook، model/version cards، القيود المعروفة، ونتائج القياس والاستثناءات.

لا ينبغي أن يخترع فريق الـ Backend حقولًا أو حالات أو سلوكًا من عنده إذا كانت تحتاج اتفاقًا مع الـ AI؛ تُحسم هذه الأمور في الـ contracts، ثم تُنفذ في الطرفين بالمعنى نفسه.

---

## 5. قائمة تحقق عامة قبل اعتبار أي Phase مكتملة

لكل Phase، اسأل الأسئلة التالية قبل الانتقال للمرحلة التالية:

- [ ] هل نفذنا المهام المرتبطة بالمرحلة من `ATLANTECH_AI_Tasks.md`؟
- [ ] هل كل Deliverable موجود بالفعل ويمكن فحصه، وليس مجرد وصف في مستند؟
- [ ] هل Acceptance Criteria قابلة للتكرار والاختبار ونتائجها موثقة؟
- [ ] هل حُسمت قرارات `OD-*` اللازمة لهذه المرحلة، أو سُجل تأجيلها وتأثيره على المراحل التالية؟
- [ ] هل سلّمنا واعتمدنا `BD-*` المطلوبة مع فريق الـ Backend؟
- [ ] هل تم اختبار contract بين الطرفين بدل الاكتفاء باختبار كل طرف منفردًا؟
- [ ] هل سلوك الفشل وعدم ترك بيانات جزئية موثق ومختبر حيث ينطبق؟
- [ ] هل الـ safety limits والـ scope المعتمد محفوظان؟
- [ ] هل تم توثيق أي بند غير مكتمل كاستثناء واضح بدل اعتباره منجزًا؟

---

## 6. ملاحظات يجب الحفاظ عليها عند استخدام هذه الخطة

- هذه الخطة مرجع لتقسيم التنفيذ والتسليمات، وليست بديلًا عن وصف كل Task في `ATLANTECH_AI_Tasks.md`.
- تفاصيل `OD-*` و`BD-*` الكاملة يجب الرجوع فيها إلى `ATLANTECH_AI_Backend_Dependencies.md`.
- لا تُعتبر أسماء الجداول والحقول وقيم الحالات نهائية إذا كانت تحتاج تحققًا من `PortSync.sql` أو FRD غير المتاحين عند إعداد الخطة الأصلية.
- لا تُعتبر Phase 7 ضمن التسليم النهائي تلقائيًا؛ هي مشروطة بجاهزية البيانات وحسم النطاق.
- يوجد سؤال نطاق مفتوح حول Bias Correction في Phase 6، لأن الخطة تذكره ضمن مخرجات المرحلة، بينما تشير ملاحظة النطاق إلى احتمال تأجيله خارج MVP. يجب حسم هذا التناقض قبل اعتماد نطاق التسليم.
- لا تُخفف hard safety limits بسبب model output أو fallback أو bias correction.

---

**نهاية المستند — ATLANTECH AI Phase Deliverables & Backend Handoffs**
