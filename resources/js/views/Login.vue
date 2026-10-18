<template>
  <div class="login-page">
    <div class="login-card">
      <h1 class="login-title">前台管理系统</h1>

      <form class="login-form" @submit.prevent="submit">
        <div class="form-item">
          <label class="label">账&emsp;号</label>
          <div class="input-wrap">
            <span class="input-icon"><QtIcon name="user" :size="19" /></span>
            <input class="input" v-model.trim="form.employee_no" placeholder="请输入工号" autocomplete="username" />
          </div>
        </div>

        <div class="form-item">
          <label class="label">密&emsp;码</label>
          <div class="input-wrap">
            <span class="input-icon"><QtIcon name="key" :size="19" /></span>
            <input class="input" type="password" v-model="form.password" placeholder="请输入密码" autocomplete="current-password" />
          </div>
        </div>

        <div class="form-row">
          <label class="check">
            <input type="checkbox" v-model="form.remember" />
            <span class="check-mark"></span>
            <span>记住账号</span>
          </label>
          <a class="link" style="color:#1e88e5;" @click="forgetVisible = true">忘记密码?</a>
        </div>

        <button class="login-btn" type="submit" :disabled="loading">
          {{ loading ? '登录中…' : '立即登录' }}
        </button>
      </form>

      <div class="login-footer">© 2026 前台管理系统 版权所有</div>
    </div>

    <div class="modal-mask" :class="{ show: forgetVisible }" @click.self="forgetVisible = false">
      <div class="modal-box">
        <div class="modal-title">温馨提示</div>
        <div class="modal-body">请联系管理员，联系方式：1123456
          <div class="modal-tip">点击空白处关闭</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useAuthStore } from '../stores/auth';
import QtIcon from '../components/QtIcon.vue';

const router = useRouter();
const auth = useAuthStore();

const form = reactive({ employee_no: '', password: '', remember: false });
const loading = ref(false);
const forgetVisible = ref(false);

async function submit() {
    if (!form.employee_no) return ElMessage.warning('请输入工号');
    if (!form.password) return ElMessage.warning('请输入密码');

    loading.value = true;
    try {
        await auth.login(form);
        ElMessage.success('登录成功');
        router.push('/dashboard');
    } catch {
        // 错误提示由拦截器/后端 message 统一处理
    } finally {
        loading.value = false;
    }
}
</script>

<style scoped>
.login-page {
  background: linear-gradient(135deg, #4ba6ff 0%, #2f80ed 100%);
  min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  position: relative; overflow: hidden;
}
.login-page::before {
  content: ''; position: absolute; top: -40px; right: 40%;
  width: 160px; height: 200px; border: 2px solid rgba(255,255,255,.15);
  transform: rotate(15deg); pointer-events: none;
}
.login-page::after {
  content: ''; position: absolute; top: 0; right: 0;
  width: 220px; height: 100px;
  border-top: 2px solid rgba(255,255,255,.25);
  border-right: 2px solid rgba(255,255,255,.25);
  pointer-events: none;
}
.login-card {
  position: relative; z-index: 1;
  width: 460px; max-width: calc(100vw - 32px);
  background: #fff; border-radius: 10px;
  padding: 40px 50px 30px;
  box-shadow: 0 20px 50px rgba(0,0,0,.15);
}
.login-title { font-size: 26px; font-weight: 700; text-align: center; margin-bottom: 30px; }
.login-form .form-item { display: flex; align-items: center; margin-bottom: 24px; }
.login-form .label { display: block; flex-shrink: 0; margin-right: 12px; font-size: 13px; color: #333; letter-spacing: 2px; }
.login-form .input-wrap {
  position: relative; border: 1px solid #dcdfe6; border-radius: 4px;
  background: #fff; transition: border-color .15s; flex: 1;
}
.login-form .input-wrap:focus-within { border-color: var(--primary); }
.login-form .input-icon {
  position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
  color: var(--primary); display: inline-flex;
}
.login-form .input {
  width: 100%; height: 44px; padding: 0 14px 0 40px;
  border: none; background: transparent; font-size: 14px; outline: none;
  box-sizing: border-box;
}
.login-form .input::placeholder { color: #c0c4cc; }
.login-form .form-row {
  display: flex; align-items: center; justify-content: space-between;
  margin: 18px 0 24px;
}
.login-form .check { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #606266; cursor: pointer; }
.login-form .check input { display: none; }
.login-form .check-mark {
  width: 14px; height: 14px; border: 1px solid #dcdfe6; border-radius: 50%;
  display: inline-block; position: relative;
}
.login-form .check input:checked + .check-mark::after {
  content: ''; position: absolute; top: 3px; left: 3px;
  width: 6px; height: 6px; border-radius: 50%; background: var(--primary);
}
.login-form .login-btn {
  width: 100%; height: 46px; background: var(--primary); color: #fff;
  border-radius: 4px; font-size: 15px; font-weight: 500; letter-spacing: 4px;
  transition: background .15s; cursor: pointer; border: none;
}
.login-form .login-btn:hover { background: var(--primary-dark); }
.login-form .login-btn:disabled { opacity: .7; cursor: not-allowed; }
.login-footer { text-align: center; color: #c0c4cc; font-size: 12px; margin-top: 30px; }
.link { cursor: pointer; text-decoration: none; }
.modal-mask {
  display: none; position: fixed; inset: 0; z-index: 9999;
  background: rgba(0,0,0,.6); align-items: center; justify-content: center;
  padding: 16px; box-sizing: border-box;
}
.modal-mask.show { display: flex; }
.modal-box {
  width: 440px; max-width: 100%; background: #fff; border-radius: 6px; overflow: hidden;
  box-shadow: 0 12px 40px rgba(0,0,0,.25); animation: modalIn .15s ease-out;
}
@keyframes modalIn { from { transform: translateY(8px); opacity: 0; } to { transform: none; opacity: 1; } }
.modal-title { background: #f5b400; color: #fff; padding: 10px 16px; font-size: 14px; font-weight: 500; }
.modal-body { padding: 24px 20px; font-size: 14px; color: #303133; line-height: 1.7; text-align: center; word-break: break-word; }
.modal-tip { margin-top: 12px; font-size: 12px; color: #909399; }
</style>
