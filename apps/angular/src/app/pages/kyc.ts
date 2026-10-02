/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, OnDestroy, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Api, ApiError, CountryOption, ID_TYPES, IdType, IdentityStatus, dt } from '../core/api.service';
import { ACCEPT, ImageUpload, MAX_BYTES } from '../core/upload';
import { Mt, Msg, T } from '../core/i18n/i18n';

/**
 * 证件类型短码 → **词条键**（短码真值见服务端 IdentityController 的 validator 白名单）。
 * 存键不存译好的串：`typeLabel()` 在模板里过 `| mt`，渲染期才查表 ⇒ 切语言跟着变。
 */
const TYPE_LABEL: Record<string, string> = {
  id_card: 'kyc.type.id_card',
  passport: 'kyc.type.passport',
  driver_license: 'kyc.type.driver_license',
};

/** 审核状态 → 词条键。服务端只下发这几种（未提交时只回 status:'not_submitted'） */
const STATUS_LABEL: Record<string, string> = {
  not_submitted: 'kyc.status.not_submitted',
  pending: 'kyc.status.pending',
  approved: 'kyc.status.approved',
  rejected: 'kyc.status.rejected',
};

/**
 * 实名认证（KYC）—— 提现档位的唯一开关：服务端 WithdrawController::withdrawLevel 只在
 * status==='approved' 时给 verified 档（更高的单笔/日/月额度、更低的费率），其余一律 default 档。
 * 也就是说这个页面直接决定用户提现能提多少。
 *
 * 照片走 aetherupload 单块直传（见 core/upload.ts），落库值是相对地址。
 * 提交后**不再回显照片**：服务端 identity/status 只回掩码姓名与审核结果，不回照片路径。
 */
@Component({
  selector: 'app-kyc',
  imports: [RouterLink, T, Mt],
  template: `
    <div class="between sect">
      <h2>{{ 'kyc.title' | t }}</h2>
      @if (st(); as s) {
        <span class="badge" [class]="badgeCls(s.status)">{{ label(s.status) | mt }}</span>
      }
    </div>

    @if (loading()) {
      <div class="card stack">
        <div class="skeleton sk-line"></div>
        <div class="skeleton sk-line w70"></div>
        <div class="skeleton sk-line w50"></div>
      </div>
    } @else if (loadErr()) {
      <div class="card state">
        <strong>{{ 'common.load_failed' | t }}</strong>
        <span>{{ loadErr() }}</span>
        <button class="btn" type="button" (click)="load()">{{ 'common.retry' | t }}</button>
      </div>
    } @else if (st(); as s) {
      <div class="card">
        <p class="hint">{{ 'kyc.hint' | t }}</p>
        <div class="kv"><span>{{ 'kyc.real_name' | t }}</span><span>{{ s.real_name || '—' }}</span></div>
        <div class="kv">
          <span>{{ 'kyc.id_type' | t }}</span><span>{{ typeLabel(s.id_type) | mt }}</span>
        </div>
        @if (s.submitted_at) {
          <div class="kv">
            <span>{{ 'kyc.submitted_at' | t }}</span><span>{{ dt(s.submitted_at) }}</span>
          </div>
        }
        @if (s.reviewed_at) {
          <div class="kv">
            <span>{{ 'kyc.reviewed_at' | t }}</span><span>{{ dt(s.reviewed_at) }}</span>
          </div>
        }
        @if (s.review_note) {
          <div class="alert">{{ s.review_note }}</div>
        }
      </div>

      @if (s.status === 'pending') {
        <div class="card state">
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
          <strong>{{ 'kyc.status.pending' | t }}</strong>
          <span>{{ 'kyc.pending_hint' | t }}</span>
        </div>
      } @else if (s.status === 'approved') {
        <div class="card state">
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
          <strong>{{ 'kyc.approved_title' | t }}</strong>
          <span>{{ 'kyc.approved_hint' | t }}</span>
          <a class="btn" routerLink="/wallet/withdraw">{{ 'kyc.go_withdraw' | t }}</a>
        </div>
      } @else {
        @if (s.status === 'rejected') {
          <div class="alert">{{ 'kyc.rejected_hint' | t }}</div>
        }
        <div class="card stack">
          <h3>{{ 'kyc.form_title' | t }}</h3>

          @if (formErr()) {
            <div class="alert">{{ formErr() | mt }}</div>
          }
          @if (okMsg()) {
            <div class="alert ok">{{ okMsg() | mt }}</div>
          }

          <label class="field">
            <span>{{ 'kyc.real_name' | t }}</span>
            <input
              class="input"
              name="realName"
              autocomplete="name"
              [placeholder]="'kyc.real_name_ph' | t"
              [value]="realName()"
              (input)="realName.set(val($event))"
            />
          </label>

          <label class="field">
            <span>{{ 'kyc.id_type' | t }}</span>
            <select class="input" [value]="idType()" (change)="pickIdType($event)">
              @for (o of types; track o) {
                <option [value]="o">{{ typeLabel(o) | mt }}</option>
              }
            </select>
          </label>

          <label class="field">
            <span>{{ 'kyc.id_number' | t }}</span>
            <input
              class="input mono"
              name="idNumber"
              autocomplete="off"
              [placeholder]="'kyc.id_number_ph' | t"
              [value]="idNumber()"
              (input)="idNumber.set(val($event))"
            />
          </label>

          <label class="field">
            <span>{{ 'kyc.country' | t }}</span>
            <!-- 选项只有在册代码；不在列表里的旧值由 countryChoices 补一条，别让它被吞成空。
                 选中态走 option 的 [selected]（同 deposit/exchange 的动态列表），
                 第一项常驻：country 是可选项，选过之后要能退回「未选择」。 -->
            <select class="input" name="country" (change)="country.set(val($event))">
              <option value="" [selected]="!country().trim()">
                {{
                  (countriesBusy()
                    ? 'common.loading'
                    : countriesError()
                      ? 'kyc.country_failed'
                      : 'kyc.country_none'
                  ) | t
                }}
              </option>
              @for (c of countryChoices(); track c) {
                <option [value]="c" [selected]="c === country().trim()">{{ c }}</option>
              }
            </select>
          </label>

          @for (p of photos; track p.key) {
            <div class="field">
              <span>{{ p.labelKey | t }}</span>
              <div class="up">
                <input
                  class="input"
                  type="file"
                  [accept]="accept"
                  [attr.aria-label]="p.labelKey | t"
                  (change)="pick($event, p.key)"
                />
                @if (upBusy()[p.key]) {
                  <span class="badge warn">{{ 'kyc.uploading' | t }}</span>
                } @else if (photo()[p.key]) {
                  <span class="badge on">{{ 'kyc.uploaded' | t }}</span>
                }
              </div>
              @if (preview()[p.key]; as src) {
                <!-- alt 留空：缩略图的意思由上面那个已经本地化的 label 承担（同 .state-art 的写法），
                     写 p.label + '预览' 会拼出一串翻不了的半截中文 -->
                <img class="shot" [src]="src" alt="" aria-hidden="true" />
              }
            </div>
          }

          <p class="hint">{{ 'kyc.upload_hint' | t }}</p>

          <button class="btn primary wide" type="button" [disabled]="busy()" (click)="submit()">
            {{ (busy() ? 'common.submitting' : 'kyc.submit') | t }}
          </button>
        </div>
      }
    }
  `,
  styles: [
    `
      .sect {
        margin: 0 0 14px;
      }
      .sect h2 {
        margin: 0;
        font-size: 17px;
      }
      .card.stack h3 {
        margin: 0;
        font-size: 15px;
      }
      .hint {
        margin: 0 0 14px;
        font-size: 13px;
        color: var(--muted);
        line-height: 1.6;
      }
      .card .kv {
        margin-top: 8px;
      }
      .card .alert {
        margin-top: 12px;
      }
      .sk-line {
        height: 16px;
        border-radius: 8px;
      }
      .w70 {
        width: 70%;
      }
      .w50 {
        width: 50%;
      }
      .up {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
      }
      .up .input {
        flex: 1;
        min-width: 0;
      }
      .shot {
        margin-top: 8px;
        max-width: 220px;
        max-height: 150px;
        border-radius: 10px;
        border: 1px solid var(--stroke);
        object-fit: cover;
      }
    `,
  ],
})
export class KycPage implements OnDestroy {
  private readonly api = inject(Api);
  private readonly uploader = inject(ImageUpload);

  protected readonly accept = ACCEPT;
  protected readonly types = ID_TYPES;
  protected readonly dt = dt;
  protected readonly loading = signal(true);
  protected readonly loadErr = signal('');
  protected readonly st = signal<IdentityStatus | null>(null);

  protected readonly realName = signal('');
  protected readonly idType = signal<IdType>('id_card');
  protected readonly idNumber = signal('');
  protected readonly country = signal('');

  /** 国家/地区选项：公开端点，只列在册国家代码。取不到不挡提交（country 是可选项） */
  protected readonly countries = signal<CountryOption[]>([]);
  protected readonly countriesBusy = signal(true);
  protected readonly countriesError = signal('');

  /**
   * 下拉选项 = 端点回的代码；**当前值不在列表里时补一条**，否则旧值会被下拉吞成空
   * （select 找不到匹配 option 就退成第一项，用户接着提交就把它抹掉了）——
   * 服务端对 country 只校验 `nullable|string|max:50`、不校验在册。
   */
  protected readonly countryChoices = computed(() => {
    const codes = this.countries().map((c) => c.country_code);
    const v = this.country().trim();
    return v && !codes.includes(v) ? [...codes, v] : codes;
  });

  /** 三个证件照字段：服务端 validator 里 id_front_photo / selfie_photo 必填、id_back_photo 可选 */
  protected readonly photos = [
    { key: 'id_front_photo', labelKey: 'kyc.photo_front' },
    { key: 'id_back_photo', labelKey: 'kyc.photo_back' },
    { key: 'selfie_photo', labelKey: 'kyc.photo_selfie' },
  ] as const;

  /** 落库值（相对地址），提交时直接发给 /user/identity/apply */
  protected readonly photo = signal<Record<string, string>>({});
  /** 本地预览的 objectURL —— 只为让用户确认选对了图，不发请求 */
  protected readonly preview = signal<Record<string, string>>({});
  protected readonly upBusy = signal<Record<string, boolean>>({});

  protected readonly busy = signal(false);
  /** 两态（服务端原文 / 词条键）—— 见 `core/i18n/i18n.ts` 的 `Msg`：异步拉回的文案不能在 set 时定稿 */
  protected readonly formErr = signal<Msg>('');
  protected readonly okMsg = signal<Msg>('');

  constructor() {
    this.load();
    // 选项是静态数据（在册国家），不随提交变化 ⇒ 只拉一次；失败只降级成「只有未选择」，不挡提交
    this.api.countries().subscribe({
      next: (r) => {
        this.countries.set(r.list ?? []);
        this.countriesBusy.set(false);
      },
      error: (e: ApiError) => {
        this.countriesError.set(e.message);
        this.countriesBusy.set(false);
      },
    });
  }

  ngOnDestroy(): void {
    // objectURL 不撤销会一直占着内存
    for (const u of Object.values(this.preview())) URL.revokeObjectURL(u);
  }

  /** 认得的短码给键（渲染期查表），认不得的**原样透出**服务端编码 —— 不能凭猜把它翻掉 */
  protected label(s: string): Msg {
    return STATUS_LABEL[s] ?? s;
  }

  protected typeLabel(t?: string): Msg {
    return t ? (TYPE_LABEL[t] ?? t) : '—';
  }

  protected badgeCls(s: string): string {
    return s === 'approved' ? 'on' : s === 'rejected' ? 'bad' : s === 'pending' ? 'warn' : '';
  }

  protected val(ev: Event): string {
    return (ev.target as HTMLInputElement | HTMLSelectElement).value;
  }

  /** select 的 value 是 string，收窄回服务端白名单类型（选项就来自同一份常量） */
  protected pickIdType(ev: Event): void {
    this.idType.set(this.val(ev) as IdType);
  }

  protected load(): void {
    this.loading.set(true);
    this.loadErr.set('');
    this.api.identityStatus().subscribe({
      next: (s) => {
        this.st.set(s);
        this.loading.set(false);
        // 驳回后再提交要覆盖原记录，回填已填过的字段少让用户重敲
        if (s.status === 'rejected') {
          this.idType.set((s.id_type as IdType) ?? 'id_card');
        }
      },
      error: (e: ApiError) => {
        this.loadErr.set(e.message);
        this.loading.set(false);
      },
    });
  }

  /** 选图 → 先本地预览，再直传；失败只清掉这一张，不影响已传成功的 */
  protected pick(ev: Event, key: string): void {
    const input = ev.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = ''; // 允许同一张图重选（不清则 change 不触发）
    if (!file) return;

    if (file.size > MAX_BYTES) {
      this.formErr.set({ key: 'kyc.err_too_big' });
      return;
    }
    this.formErr.set('');

    const old = this.preview()[key];
    if (old) URL.revokeObjectURL(old);
    const url = URL.createObjectURL(file);
    this.preview.update((m) => ({ ...m, [key]: url }));
    this.upBusy.update((m) => ({ ...m, [key]: true }));

    this.uploader
      .image(file)
      .then((path) => {
        this.photo.update((m) => ({ ...m, [key]: path }));
      })
      .catch((e: unknown) => {
        this.photo.update((m) => {
          const { [key]: _drop, ...rest } = m;
          return rest;
        });
        this.formErr.set(e instanceof Error ? e.message : { key: 'kyc.upload_failed' });
      })
      .finally(() => this.upBusy.update((m) => ({ ...m, [key]: false })));
  }

  protected submit(): void {
    if (this.busy()) return;
    const name = this.realName().trim();
    const num = this.idNumber().trim();
    const front = this.photo()['id_front_photo'] ?? '';
    const selfie = this.photo()['selfie_photo'] ?? '';

    // 本地先挡一遍，避免明知 422 还发请求；服务端仍会二次校验
    if (!name) return this.formErr.set({ key: 'kyc.err_name_required' });
    if (!num) return this.formErr.set({ key: 'kyc.err_number_required' });
    if (Object.values(this.upBusy()).some(Boolean)) return this.formErr.set({ key: 'kyc.err_uploading' });
    if (!front) return this.formErr.set({ key: 'kyc.err_front_required' });
    if (!selfie) return this.formErr.set({ key: 'kyc.err_selfie_required' });

    this.busy.set(true);
    this.formErr.set('');
    this.okMsg.set('');
    this.api
      .applyIdentity({
        real_name: name,
        id_type: this.idType(),
        id_number: num,
        id_front_photo: front,
        ...(this.photo()['id_back_photo']
          ? { id_back_photo: this.photo()['id_back_photo']! }
          : {}),
        selfie_photo: selfie,
        ...(this.country().trim() ? { country: this.country().trim() } : {}),
      })
      .subscribe({
        next: () => {
          this.busy.set(false);
          this.okMsg.set({ key: 'kyc.submitted' });
          // 回读真状态，不凭「请求成功」宣布结果
          this.load();
        },
        error: (e: ApiError) => {
          this.busy.set(false);
          this.formErr.set(e.message);
        },
      });
  }
}
