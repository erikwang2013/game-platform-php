/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, OnDestroy, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Api, ApiError, ID_TYPES, IdType, IdentityStatus, dt } from '../core/api.service';
import { ACCEPT, ImageUpload, MAX_BYTES } from '../core/upload';

/** 证件类型短码 → 中文（真值见服务端 IdentityController 的 validator 白名单） */
const TYPE_LABEL: Record<string, string> = {
  id_card: '身份证',
  passport: '护照',
  driver_license: '驾照',
};

/** 审核状态 → 展示文案。服务端只下发这几种（未提交时只回 status:'not_submitted'） */
const STATUS_LABEL: Record<string, string> = {
  not_submitted: '未提交',
  pending: '审核中',
  approved: '已认证',
  rejected: '已驳回',
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
  imports: [RouterLink],
  template: `
    <div class="between sect">
      <h2>实名认证</h2>
      @if (st(); as s) {
        <span class="badge" [class]="badgeCls(s.status)">{{ label(s.status) }}</span>
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
        <strong>加载失败</strong>
        <span>{{ loadErr() }}</span>
        <button class="btn" type="button" (click)="load()">重试</button>
      </div>
    } @else if (st(); as s) {
      <div class="card">
        <p class="hint">
          认证通过后提现额度按 <b>已认证档</b> 计（更高单笔/日/月额度、更低费率）；
          未认证或审核中按默认档计。证件信息仅用于合规审核，不会对外展示。
        </p>
        <div class="kv"><span>姓名</span><span>{{ s.real_name || '—' }}</span></div>
        <div class="kv"><span>证件类型</span><span>{{ typeLabel(s.id_type) }}</span></div>
        @if (s.submitted_at) {
          <div class="kv"><span>提交时间</span><span>{{ dt(s.submitted_at) }}</span></div>
        }
        @if (s.reviewed_at) {
          <div class="kv"><span>审核时间</span><span>{{ dt(s.reviewed_at) }}</span></div>
        }
        @if (s.review_note) {
          <div class="alert">{{ s.review_note }}</div>
        }
      </div>

      @if (s.status === 'pending') {
        <div class="card state">
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
          <strong>审核中</strong>
          <span>通常 1-2 个工作日内出结果，通过后提现额度自动升级，无需再操作。</span>
        </div>
      } @else if (s.status === 'approved') {
        <div class="card state">
          <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
          <strong>已完成认证</strong>
          <span>你的提现按已认证档计。</span>
          <a class="btn" routerLink="/wallet/withdraw">去提现</a>
        </div>
      } @else {
        @if (s.status === 'rejected') {
          <div class="alert">上次提交被驳回，可按下面的驳回原因修改后重新提交（会覆盖原记录）。</div>
        }
        <div class="card stack">
          <h3>提交认证资料</h3>

          @if (formErr()) {
            <div class="alert">{{ formErr() }}</div>
          }
          @if (okMsg()) {
            <div class="alert ok">{{ okMsg() }}</div>
          }

          <label class="field">
            <span>真实姓名</span>
            <input
              class="input"
              name="realName"
              autocomplete="name"
              placeholder="与证件一致"
              [value]="realName()"
              (input)="realName.set(val($event))"
            />
          </label>

          <label class="field">
            <span>证件类型</span>
            <select class="input" [value]="idType()" (change)="pickIdType($event)">
              @for (t of types; track t) {
                <option [value]="t">{{ typeLabel(t) }}</option>
              }
            </select>
          </label>

          <label class="field">
            <span>证件号码</span>
            <input
              class="input mono"
              name="idNumber"
              autocomplete="off"
              placeholder="证件上的号码"
              [value]="idNumber()"
              (input)="idNumber.set(val($event))"
            />
          </label>

          <label class="field">
            <span>国家/地区（可选）</span>
            <input
              class="input"
              name="country"
              autocomplete="country-name"
              placeholder="如 CN"
              [value]="country()"
              (input)="country.set(val($event))"
            />
          </label>

          @for (p of photos; track p.key) {
            <div class="field">
              <span>{{ p.label }}</span>
              <div class="up">
                <input
                  class="input"
                  type="file"
                  [accept]="accept"
                  [attr.aria-label]="p.label"
                  (change)="pick($event, p.key)"
                />
                @if (upBusy()[p.key]) {
                  <span class="badge warn">上传中…</span>
                } @else if (photo()[p.key]) {
                  <span class="badge on">已上传</span>
                }
              </div>
              @if (preview()[p.key]; as src) {
                <img class="shot" [src]="src" [alt]="p.label + '预览'" />
              }
            </div>
          }

          <p class="hint">支持 jpg / png / gif / webp，单张不超过 5MB。</p>

          <button class="btn primary wide" type="button" [disabled]="busy()" (click)="submit()">
            {{ busy() ? '提交中…' : '提交认证' }}
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

  /** 三个证件照字段：服务端 validator 里 id_front_photo / selfie_photo 必填、id_back_photo 可选 */
  protected readonly photos = [
    { key: 'id_front_photo', label: '证件正面照' },
    { key: 'id_back_photo', label: '证件背面照（可选）' },
    { key: 'selfie_photo', label: '手持自拍照' },
  ] as const;

  /** 落库值（相对地址），提交时直接发给 /user/identity/apply */
  protected readonly photo = signal<Record<string, string>>({});
  /** 本地预览的 objectURL —— 只为让用户确认选对了图，不发请求 */
  protected readonly preview = signal<Record<string, string>>({});
  protected readonly upBusy = signal<Record<string, boolean>>({});

  protected readonly busy = signal(false);
  protected readonly formErr = signal('');
  protected readonly okMsg = signal('');

  constructor() {
    this.load();
  }

  ngOnDestroy(): void {
    // objectURL 不撤销会一直占着内存
    for (const u of Object.values(this.preview())) URL.revokeObjectURL(u);
  }

  protected label(s: string): string {
    return STATUS_LABEL[s] ?? s;
  }

  protected typeLabel(t?: string): string {
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
      this.formErr.set('图片超过 5MB，请压缩后再试');
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
        this.formErr.set(e instanceof Error ? e.message : '上传失败，请重试');
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
    if (!name) return this.formErr.set('请填写真实姓名');
    if (!num) return this.formErr.set('请填写证件号码');
    if (Object.values(this.upBusy()).some(Boolean)) return this.formErr.set('照片仍在上传中，请稍候');
    if (!front) return this.formErr.set('请上传证件正面照');
    if (!selfie) return this.formErr.set('请上传手持自拍照');

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
          this.okMsg.set('已提交，等待审核');
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
