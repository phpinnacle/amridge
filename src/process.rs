use std::io::Error;
use std::sync::Mutex;
use std::time::Instant;
use std::process::{Stdio};
use tokio::prelude::*;
use tokio::process::{Command, Child, ChildStdin, ChildStdout};

pub struct ProcessPool {
    workers: Mutex<Vec<Process>>,
}

impl ProcessPool {
    pub async fn create(num: u32, cmd: String, args: Vec<String>) -> Result<ProcessPool, Error> {
        let mut items: Vec<Process> = Vec::new();

        for _ in 0..num {
            items.push(Process::spawn(&cmd, &args).await?);
        }

        let mutex = Mutex::new(items);

        Ok(ProcessPool {
            workers: mutex
        })
    }

    pub fn pop(&self) -> Process {
        let mut workers = self.workers.lock()
            .expect("Unable to lock workers for pop");

        return workers.pop().unwrap();
    }

    pub fn push(&self, process: Process) {
        let mut workers = self.workers.lock()
            .expect("Unable to lock workers for push");

        workers.push(process);
    }
}

#[derive(Debug)]
pub struct Process {
    instance: Child,
    stdin: ChildStdin,
    stdout: ChildStdout,
    started: Instant,
}

impl Process {
    pub async fn spawn(cmd: &String, args: &Vec<String>) -> Result<Process, Error> {
        let mut instance = Command::new(cmd)
            .args(args)
            .stdin(Stdio::piped())
            .stdout(Stdio::piped())
            .spawn()?;

        let stdin = instance.stdin().take().unwrap();
        let stdout = instance.stdout().take().unwrap();

        Ok(Process {
            instance,
            stdin,
            stdout,
            started: Instant::now(),
        })
    }

   pub fn pid(&self) -> u32 {
       self.instance.id()
   }

   pub fn kill(&mut self) {
       self.instance.kill().expect("NoError");
   }

   pub async fn write(&mut self, data: Vec<u8>) -> Result<(), Error> {
       Ok(self.stdin.write_all(data.as_ref()).await?)
   }

   pub async fn read(&mut self, num: usize) -> Result<Vec<u8>, Error> {
       let mut out = vec![0u8; num];

       self.stdout.read_exact(&mut out).await?;

       Ok(out)
   }
}
