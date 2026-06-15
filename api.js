(function(){
  const API_BASE='/vr/calltrack/api';
  const ENDPOINTS={
    calls:`${API_BASE}/get_calls.php`,
    dashboard:`${API_BASE}/dashboard.php`
  };

  function buildQuery(filters={}){
    const params=new URLSearchParams();
    ['manager','phone','user_phone','date_from','date_to'].forEach((key)=>{
      const value=filters[key];
      if(value!==undefined&&value!==null&&String(value).trim()!=='') params.set(key,value);
    });
    const query=params.toString();
    return query?`?${query}`:'';
  }

  async function requestJson(url, options){
    const response=await fetch(url, options);
    if(!response.ok) throw new Error(`API ${response.status}: ${url}`);
    const text=await response.text();
    if(!text.trim()) return null;
    try{return JSON.parse(text);}catch(error){throw new Error(`Некорректный JSON от API: ${url}`);}
  }

  function unwrapList(payload){
    if(Array.isArray(payload)) return payload;
    if(Array.isArray(payload?.data)) return payload.data;
    if(Array.isArray(payload?.calls)) return payload.calls;
    if(payload?.status==='error') throw new Error(payload.message||'API_ERROR');
    return [];
  }

  async function loadCalls(filters={}){
    return unwrapList(await requestJson(`${ENDPOINTS.calls}${buildQuery(filters)}`));
  }

  async function loadDashboard(filters={}){
    return requestJson(`${ENDPOINTS.dashboard}${buildQuery(filters)}`);
  }

  window.calltrackApi=window.calltrackApi||{};
  window.calltrackApi.endpoints={...ENDPOINTS,...(window.calltrackApi.endpoints||{})};
  window.calltrackApi.loadCalls=loadCalls;
  window.calltrackApi.loadDashboard=loadDashboard;
})();
