(function(){
  const API_BASE='/vr/calltrack/api';
  const ENDPOINTS={
    calls:`${API_BASE}/get_calls.php`,
    dashboard:`${API_BASE}/dashboard.php`,
    deleteCalls:`${API_BASE}/delete_calls.php`
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

  function isDeleteSuccess(data){
    if(data===null) return true;
    if(Array.isArray(data)) return false;
    if(data?.status==='success'||data?.ok===true||data?.success===true) return true;
    return Number(data?.deleted||data?.affected||data?.affected_rows||0)>0;
  }

  async function deleteCalls(records=[]){
    const ids=records.map((record)=>record.sqlId||record.sql_id||record.ID).filter(Boolean);
    const callIds=records.map((record)=>record.callId||record.call_id||record.id).filter(Boolean);
    const payload={ids,call_ids:callIds};
    const requests=[
      [ENDPOINTS.deleteCalls,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}],
      [ENDPOINTS.calls,{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}],
      [ENDPOINTS.calls,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',...payload})}]
    ];
    let lastError=null;
    for(const [url,options] of requests){
      try{
        const data=await requestJson(url,options);
        if(data?.status==='error') throw new Error(data.message||'DELETE_ERROR');
        if(!isDeleteSuccess(data)) throw new Error('DELETE_NOT_CONFIRMED');
        return data;
      }catch(error){
        lastError=error;
      }
    }
    throw lastError||new Error('DELETE_ERROR');
  }

  window.calltrackApi=window.calltrackApi||{};
  window.calltrackApi.endpoints={...ENDPOINTS,...(window.calltrackApi.endpoints||{})};
  window.calltrackApi.loadCalls=loadCalls;
  window.calltrackApi.loadDashboard=loadDashboard;
  window.calltrackApi.deleteCalls=deleteCalls;
})();
